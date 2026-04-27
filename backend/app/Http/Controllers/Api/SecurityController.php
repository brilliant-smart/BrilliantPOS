<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SecurityController extends Controller
{
    // Security Settings
    public function getSettings()
    {
        return response()->json(DB::table('security_settings')->first());
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

        DB::table('security_settings')->update($validated);

        return response()->json(['message' => 'Security settings updated successfully']);
    }

    // IP Whitelist
    public function getWhitelist()
    {
        $whitelist = DB::table('ip_whitelist')->get();
        return response()->json($whitelist);
    }

    public function addToWhitelist(Request $request)
    {
        $validated = $request->validate([
            'ip_address' => 'required|ip|unique:ip_whitelist,ip_address',
            'description' => 'nullable|string',
        ]);

        $id = DB::table('ip_whitelist')->insertGetId([
            'ip_address' => $validated['ip_address'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'IP added to whitelist', 'id' => $id], 201);
    }

    public function removeFromWhitelist($id)
    {
        DB::table('ip_whitelist')->where('id', $id)->delete();
        return response()->json(['message' => 'IP removed from whitelist']);
    }

    // Activity Logs (already implemented in AuditLogController)
    
    // Login Attempts
    public function getLoginAttempts(Request $request)
    {
        $query = DB::table('login_attempts')->latest('attempted_at');

        if ($request->filled('email')) {
            $query->where('email', $request->email);
        }

        if ($request->filled('ip_address')) {
            $query->where('ip_address', $request->ip_address);
        }

        $attempts = $query->paginate(20);
        return response()->json($attempts);
    }

    // Two-Factor Authentication
    public function enable2FA(Request $request)
    {
        $user = $request->user();
        
        // Generate secret
        $secret = $this->generateSecret();
        
        DB::table('two_factor_auth')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'enabled' => true,
                'secret' => $secret,
                'recovery_codes' => json_encode($this->generateRecoveryCodes()),
                'enabled_at' => now(),
                'updated_at' => now(),
            ]
        );

        return response()->json([
            'message' => '2FA enabled successfully',
            'secret' => $secret,
        ]);
    }

    public function disable2FA(Request $request)
    {
        $user = $request->user();
        
        DB::table('two_factor_auth')
            ->where('user_id', $user->id)
            ->update(['enabled' => false]);

        return response()->json(['message' => '2FA disabled successfully']);
    }

    private function generateSecret()
    {
        return bin2hex(random_bytes(16));
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
