<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SecurityControllerTest extends TestCase
{
    use RefreshDatabase;

    private function ensureSecuritySettings(): void
    {
        // The migration seeds a default row; use updateOrCreate to avoid conflicts
        \App\Models\SecuritySetting::updateOrCreate(
            ['id' => 1],
            [
                'ip_whitelist_enabled' => false,
                'two_fa_required' => false,
                'password_min_length' => 8,
                'password_require_uppercase' => true,
                'password_require_numbers' => true,
                'password_require_symbols' => false,
                'password_expiry_days' => 90,
                'max_login_attempts' => 5,
                'lockout_duration_minutes' => 15,
                'session_timeout_minutes' => 120,
            ]
        );
        Cache::flush();
    }

    // ---- Settings ----

    public function test_owner_can_get_security_settings(): void
    {
        $this->ensureSecuritySettings();

        $response = $this->actingAsOwner()->getJson('/api/security/settings');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'ip_whitelist_enabled',
            'two_fa_required',
            'password_min_length',
            'max_login_attempts',
        ]);
    }

    public function test_owner_can_update_security_settings(): void
    {
        $this->ensureSecuritySettings();
        Cache::flush();

        $response = $this->actingAsOwner()->putJson('/api/security/settings', [
            'max_login_attempts' => 10,
            'lockout_duration_minutes' => 30,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('security_settings', [
            'max_login_attempts' => 10,
            'lockout_duration_minutes' => 30,
        ]);
    }

    public function test_cashier_cannot_update_security_settings(): void
    {
        $this->ensureSecuritySettings();

        $response = $this->actingAsCashier()->putJson('/api/security/settings', [
            'max_login_attempts' => 99,
        ]);

        $response->assertStatus(403);
    }

    public function test_manager_cannot_update_security_settings(): void
    {
        $this->ensureSecuritySettings();

        $response = $this->actingAsManager()->putJson('/api/security/settings', [
            'max_login_attempts' => 99,
        ]);

        $response->assertStatus(403);
    }

    // ---- IP Whitelist ----

    public function test_owner_can_list_ip_whitelist(): void
    {
        $this->ensureSecuritySettings();

        $response = $this->actingAsOwner()->getJson('/api/security/ip-whitelist');

        $response->assertStatus(200);
    }

    public function test_owner_can_add_ip_to_whitelist(): void
    {
        $this->ensureSecuritySettings();
        Cache::flush();

        $response = $this->actingAsOwner()->postJson('/api/security/ip-whitelist', [
            'ip_address' => '203.0.113.50',
            'description' => 'Office desktop',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('ip_whitelist', [
            'ip_address' => '203.0.113.50',
            'description' => 'Office desktop',
            'is_active' => true,
        ]);
    }

    public function test_duplicate_ip_rejected(): void
    {
        $this->ensureSecuritySettings();
        Cache::flush();

        $this->actingAsOwner()->postJson('/api/security/ip-whitelist', [
            'ip_address' => '203.0.113.50',
            'description' => 'First entry',
        ]);

        $response = $this->actingAsOwner()->postJson('/api/security/ip-whitelist', [
            'ip_address' => '203.0.113.50',
            'description' => 'Duplicate',
        ]);

        $response->assertStatus(422);
    }

    public function test_owner_can_remove_ip_from_whitelist(): void
    {
        $this->ensureSecuritySettings();
        Cache::flush();

        $ip = \App\Models\IpWhitelist::create([
            'ip_address' => '203.0.113.1',
            'description' => 'Test IP',
            'is_active' => true,
        ]);

        $response = $this->actingAsOwner()->deleteJson("/api/security/ip-whitelist/{$ip->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('ip_whitelist', ['id' => $ip->id]);
    }

    public function test_cashier_cannot_manage_ip_whitelist(): void
    {
        $this->ensureSecuritySettings();

        $response = $this->actingAsCashier()->postJson('/api/security/ip-whitelist', [
            'ip_address' => '1.2.3.4',
        ]);

        $response->assertStatus(403);
    }

    // ---- 2FA ----

    public function test_owner_can_enable_2fa(): void
    {
        $this->ensureSecuritySettings();
        $user = User::factory()->create(['role' => 'owner', 'is_active' => true]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/security/2fa/enable');

        $response->assertStatus(200);
        $response->assertJsonStructure(['secret', 'otpauth_uri']);
        $this->assertDatabaseHas('two_factor_auth', ['user_id' => $user->id, 'enabled' => false]);
    }

    public function test_2fa_confirm_with_invalid_code_fails(): void
    {
        $this->ensureSecuritySettings();
        $user = User::factory()->create(['role' => 'owner', 'is_active' => true]);

        $this->actingAs($user, 'sanctum')->postJson('/api/security/2fa/enable');

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/security/2fa/confirm', [
            'code' => '000000',
        ]);

        $response->assertStatus(422);
    }

    public function test_2fa_confirm_without_prior_enable_fails(): void
    {
        $this->ensureSecuritySettings();
        $user = User::factory()->create(['role' => 'owner', 'is_active' => true]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/security/2fa/confirm', [
            'code' => '123456',
        ]);

        $response->assertStatus(400);
    }

    public function test_cashier_cannot_enable_2fa(): void
    {
        $this->ensureSecuritySettings();

        $response = $this->actingAsCashier()->postJson('/api/security/2fa/enable');

        $response->assertStatus(403);
    }

    // ---- Login Attempts ----

    public function test_owner_can_view_login_attempts(): void
    {
        $this->ensureSecuritySettings();

        $response = $this->actingAsOwner()->getJson('/api/security/login-attempts');

        $response->assertStatus(200);
    }
}