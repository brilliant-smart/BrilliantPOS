<?php

namespace Tests\Unit\Services;

use App\Models\NotificationSetting;
use App\Models\Product;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(NotificationService::class);
    }

    // ---- getSettings ----

    public function test_get_settings_includes_low_stock_alert(): void
    {
        $settings = $this->service->getSettings();

        $lowStock = $settings->firstWhere('key', 'low_stock_alert');
        $this->assertNotNull($lowStock, 'low_stock_alert setting must exist');
        $this->assertEquals('low_stock_alert', $lowStock->key);
    }

    // ---- updateSetting ----

    public function test_update_setting_changes_value(): void
    {
        $setting = $this->service->updateSetting('low_stock_alert', [
            'email_enabled' => false,
        ]);

        $this->assertFalse($setting->email_enabled);
        $this->assertEquals('low_stock_alert', $setting->key);
    }

    public function test_update_nonexistent_setting_throws_exception(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->service->updateSetting('nonexistent_key', ['email_enabled' => true]);
    }

    // ---- sendLowStockAlert ----

    public function test_send_low_stock_alert_creates_log_when_enabled(): void
    {
        Mail::fake();

        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        NotificationSetting::where('key', 'low_stock_alert')->update(['email_enabled' => true]);
        $product = Product::factory()->create([
            'name' => 'Low Stock Item',
            'sku' => 'LS-001',
            'stock_quantity' => 2,
            'reorder_point' => 10,
            'is_active' => true,
        ]);

        $this->service->sendLowStockAlert($product);

        $this->assertDatabaseHas('notification_logs', [
            'category' => 'low_stock',
            'type' => 'email',
        ]);
    }

    public function test_send_low_stock_alert_skips_when_disabled(): void
    {
        Mail::fake();

        User::factory()->create(['role' => 'owner', 'is_active' => true]);
        NotificationSetting::where('key', 'low_stock_alert')->update([
            'email_enabled' => false,
            'sms_enabled' => false,
            'in_app_enabled' => false,
        ]);
        $product = Product::factory()->create([
            'stock_quantity' => 2,
            'reorder_point' => 10,
            'is_active' => true,
        ]);

        $this->service->sendLowStockAlert($product);

        // No logs should be created when all channels are disabled
        $this->assertDatabaseMissing('notification_logs', [
            'category' => 'low_stock',
        ]);
    }

    // ---- getLogs ----

    public function test_get_logs_returns_paginated_results(): void
    {
        $result = $this->service->getLogs();

        $this->assertArrayHasKey('data', $result->toArray());
        $this->assertArrayHasKey('current_page', $result->toArray());
    }
}