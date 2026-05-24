<?php

namespace Tests\Feature\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckIPWhitelistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_whitelist_disabled_allows_all_ips(): void
    {
        // The migration seeds a row with ip_whitelist_enabled = false
        $this->actingAsOwner();

        $response = $this->getJson('/api/admin/products');

        $response->assertStatus(200);
    }

    public function test_whitelist_enabled_blocks_non_whitelisted_ip(): void
    {
        // Update the existing seeded row to enable whitelisting
        DB::table('security_settings')->where('id', 1)->update([
            'ip_whitelist_enabled' => true,
            'updated_at' => now(),
        ]);
        Cache::flush();

        $this->actingAsOwner();

        // No IP whitelisted — should be blocked
        $response = $this->getJson('/api/admin/products');

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Access denied. Your IP address is not whitelisted.']);
    }

    public function test_whitelist_enabled_allows_whitelisted_ip(): void
    {
        DB::table('security_settings')->where('id', 1)->update([
            'ip_whitelist_enabled' => true,
            'updated_at' => now(),
        ]);
        Cache::flush();

        $this->actingAsOwner();

        // Add current IP to whitelist
        DB::table('ip_whitelist')->insert([
            'ip_address' => '127.0.0.1',
            'is_active' => true,
            'description' => 'Test IP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/admin/products');

        $response->assertStatus(200);
    }

    public function test_inactive_ip_is_not_allowed(): void
    {
        DB::table('security_settings')->where('id', 1)->update([
            'ip_whitelist_enabled' => true,
            'updated_at' => now(),
        ]);
        Cache::flush();

        $this->actingAsOwner();

        // Add IP but inactive
        DB::table('ip_whitelist')->insert([
            'ip_address' => '127.0.0.1',
            'is_active' => false,
            'description' => 'Test IP',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/admin/products');

        $response->assertStatus(403);
    }

    public function test_no_security_settings_allows_all_ips(): void
    {
        // Delete the seeded row to simulate no settings
        DB::table('security_settings')->delete();
        Cache::flush();

        $this->actingAsOwner();

        $response = $this->getJson('/api/admin/products');

        $response->assertStatus(200);
    }
}