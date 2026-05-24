<?php

namespace Tests\Feature\Controllers;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SettingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        // Ensure test-specific settings exist (migration seeds defaults already)
        \DB::table('settings')->insertOrIgnore([
            ['key' => 'store_name', 'value' => 'Test Store', 'type' => 'string', 'group' => 'general', 'label' => 'Store Name', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'currency_symbol', 'value' => '₦', 'type' => 'string', 'group' => 'general', 'label' => 'Currency Symbol', 'created_at' => now(), 'updated_at' => now()],
        ]);
        // Update vat_rate to a known value for type-casting test
        \DB::table('settings')->where('key', 'vat_rate')->update(['value' => '7.5', 'type' => 'float']);
    }

    // ---- Index ----

    public function test_owner_can_list_settings(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/settings');

        $response->assertStatus(200);
    }

    public function test_settings_are_grouped_by_group(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/settings');

        $response->assertStatus(200);
        $data = $response->json();
        // The response groups settings by group name
        $this->assertArrayHasKey('general', $data);
        // vat_rate is in the 'general' group per the default migration seed
    }

    public function test_settings_type_casting(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/settings');

        $data = $response->json();
        // The response structure is {group: {key: casted_value}}
        // vat_rate is in the 'general' group per the migration
        $this->assertArrayHasKey('general', $data);
        $this->assertArrayHasKey('vat_rate', $data['general']);
        // Float type setting should be numeric (float or int), not string
        $this->assertTrue(
            is_float($data['general']['vat_rate']) || is_int($data['general']['vat_rate']),
            'VAT rate should be numeric, got: ' . gettype($data['general']['vat_rate'])
        );
    }

    // ---- Update ----

    public function test_owner_can_update_settings(): void
    {
        Cache::flush();

        $response = $this->actingAsOwner()->putJson('/api/settings', [
            'settings' => [
                ['key' => 'store_name', 'value' => 'Updated Store Name'],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('settings', [
            'key' => 'store_name',
            'value' => 'Updated Store Name',
        ]);
    }

    public function test_settings_update_clears_cache(): void
    {
        // Warm the cache
        Setting::get('store_name');

        $this->actingAsOwner()->putJson('/api/settings', [
            'settings' => [
                ['key' => 'store_name', 'value' => 'Cache Cleared Store'],
            ],
        ]);

        // After update, cache should be cleared and new value returned
        $this->assertEquals('Cache Cleared Store', Setting::get('store_name'));
    }

    public function test_cannot_update_nonexistent_setting_key(): void
    {
        $response = $this->actingAsOwner()->putJson('/api/settings', [
            'settings' => [
                ['key' => 'nonexistent_setting', 'value' => 'some value'],
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_cashier_cannot_update_settings(): void
    {
        $response = $this->actingAsCashier()->putJson('/api/settings', [
            'settings' => [
                ['key' => 'store_name', 'value' => 'Cashier Store'],
            ],
        ]);

        $response->assertStatus(403);
    }

    public function test_manager_cannot_update_settings(): void
    {
        $response = $this->actingAsManager()->putJson('/api/settings', [
            'settings' => [
                ['key' => 'store_name', 'value' => 'Manager Store'],
            ],
        ]);

        $response->assertStatus(403);
    }
}