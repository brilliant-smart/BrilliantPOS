<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SecuritySetting;
use App\Models\IpWhitelist;
use App\Models\LoginAttempt;
use App\Models\TwoFactorAuth;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    // Security Settings
    public function getSettings()
    {
        return response()->json(SecuritySetting::first());
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'ip_whitelist_enabled' => 'nullable|boolean',
            'two_fa_required' => 'nullable|boolean',
            'password_min_length' => 'nullable|integer|min:6|max:128',
            'password_require_uppercase' => 'nullable|boolean',
            'password_require_numbers' => 'nullable|boolean',
            'password_require_symbols' => 'nullable|boolean',
            'password_expiry_days' => 'nullable|integer|min:0',
            'max_login_attempts' => 'nullable|integer|min:1',
            'lockout_duration_minutes' => 'nullable|integer|min:1',
            'session_timeout_minutes' => 'nullable|integer|min:1',
        ]);

        SecuritySetting::first()->update($validated);

        AuditLog::logAction('security_settings.update', 'SecuritySetting', null, null, $validated, 'Security settings updated');

        return response()->json(['message' => 'Security settings updated successfully']);
    }

    // IP Whitelist
    public function getWhitelist()
    {
        return response()->json(IpWhitelist::all());
    }

    public function addToWhitelist(Request $request)
    {
        $validated = $request->validate([
            'ip_address' => 'required|ip|unique:ip_whitelist,ip_address',
            'description' => 'nullable|string',
        ]);

        $whitelist = IpWhitelist::create([
            'ip_address' => $validated['ip_address'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        AuditLog::logAction('ip_whitelist.create', 'IpWhitelist', $whitelist->id, null, $validated, "IP {$validated['ip_address']} added to whitelist");

        return response()->json(['message' => 'IP added to whitelist', 'id' => $whitelist->id], 201);
    }

    public function removeFromWhitelist($id)
    {
        IpWhitelist::destroy($id);

        AuditLog::logAction('ip_whitelist.delete', 'IpWhitelist', $id, null, null, "IP whitelist entry removed");

        return response()->json(['message' => 'IP removed from whitelist']);
    }

    // Login Attempts
    public function getLoginAttempts(Request $request)
    {
        $query = LoginAttempt::latest('attempted_at');

        if ($request->filled('email')) {
            $query->where('email', $request->email);
        }

        if ($request->filled('ip_address')) {
            $query->where('ip_address', $request->ip_address);
        }

        return response()->json($query->paginate(20));
    }

    // Two-Factor Authentication
    public function enable2FA(Request $request)
    {
        $user = $request->user();

        // Step 1: Generate secret and QR URI, but do NOT enable yet.
        // The user must verify a TOTP code via confirm2FA before it's activated.
        $secret = $this->generateSecret();

        TwoFactorAuth::updateOrCreate(
            ['user_id' => $user->id],
            [
                'enabled' => false,
                'secret' => $secret,
                'recovery_codes' => $this->generateRecoveryCodes(),
                'enabled_at' => null,
            ]
        );

        $otpauthUri = $this->generateOtpAuthUri($user, $secret);

        AuditLog::logAction('2fa.setup_initiated', 'User', $user->id, null, null, "2FA setup initiated for {$user->name}");

        return response()->json([
            'message' => 'Scan the QR code with your authenticator app, then confirm with a code',
            'secret' => $secret,
            'otpauth_uri' => $otpauthUri,
        ]);
    }

    public function confirm2FA(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();
        $twoFA = TwoFactorAuth::where('user_id', $user->id)->first();

        if (!$twoFA || !$twoFA->secret) {
            return response()->json(['message' => '2FA setup not initiated. Call enable2FA first.'], 400);
        }

        // Verify the TOTP code
        if (!$this->verifyTotp($twoFA->secret, $request->code)) {
            return response()->json(['message' => 'Invalid verification code. Try again.'], 422);
        }

        // Now actually enable 2FA
        $twoFA->update([
            'enabled' => true,
            'enabled_at' => now(),
        ]);

        AuditLog::logAction('2fa.enable', 'User', $user->id, null, null, "2FA confirmed and enabled for {$user->name}");

        return response()->json([
            'message' => '2FA enabled successfully',
            'recovery_codes' => $twoFA->recovery_codes,
        ]);
    }

    public function disable2FA(Request $request)
    {
        $user = $request->user();

        // Require password confirmation to disable 2FA
        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        if (!\Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid password'], 422);
        }

        TwoFactorAuth::where('user_id', $user->id)
            ->update(['enabled' => false]);

        AuditLog::logAction('2fa.disable', 'User', $user->id, null, null, "2FA disabled for {$user->name}");

        return response()->json(['message' => '2FA disabled successfully']);
    }

    private function generateSecret()
    {
        // Generate a Base32-compatible secret for TOTP
        $bytes = random_bytes(20);
        $secret = '';
        for ($i = 0; $i < 20; $i++) {
            $secret .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'[ord($bytes[$i]) & 31];
        }
        return $secret;
    }

    private function generateOtpAuthUri($user, string $secret): string
    {
        $issuer = urlencode(config('app.name', 'BrilliantPOS'));
        $accountName = urlencode($user->email);
        return "otpauth://totp/{$issuer}:{$accountName}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }

    private function verifyTotp(string $secret, string $code, int $window = 1): bool
    {
        $timeSlice = floor(time() / 30);

        for ($i = -$window; $i <= $window; $i++) {
            $calculatedCode = $this->calculateTotp($secret, $timeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    private function calculateTotp(string $secret, float $timeSlice): string
    {
        // Base32 decode
        $key = $this->base32Decode($secret);

        // Time as 8-byte big-endian
        $time = pack('N*', 0, $timeSlice);

        // HMAC-SHA1
        $hash = hash_hmac('sha1', $time, $key, true);

        // Dynamic truncation
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;

        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32charsFlipped = array_flip(str_split($base32chars));

        $padded = str_pad($secret, (int) (ceil(strlen($secret) / 8) * 8), '=');
        $decoded = '';

        for ($i = 0; $i < strlen($padded); $i += 8) {
            $chunk = 0;
            for ($j = 0; $j < 8; $j++) {
                if ($padded[$i + $j] !== '=') {
                    $chunk = ($chunk << 5) | $base32charsFlipped[$padded[$i + $j]];
                }
            }
            for ($j = 4; $j >= 0; $j--) {
                if ($i + $j < strlen($secret)) {
                    $decoded .= chr(($chunk >> ($j * 8)) & 0xff);
                }
            }
        }

        return $decoded;
    }

    private function generateRecoveryCodes()
    {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }
        return $codes;
    }
}