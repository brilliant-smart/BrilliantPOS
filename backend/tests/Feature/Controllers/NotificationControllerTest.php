<?php

namespace Tests\Feature\Controllers;

use App\Models\NotificationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function getOrCreateSetting(string $key = 'low_stock_alert'): NotificationSetting
    {
        return NotificationSetting::firstOrCreate(
            ['key' => $key],
            [
                'name' => ucfirst(str_replace('_', ' ', $key)),
                'description' => 'Test notification setting',
                'email_enabled' => true,
                'sms_enabled' => false,
                'in_app_enabled' => true,
                'threshold_value' => 10,
            ]
        );
    }

    // ---- Get Settings ----

    public function test_owner_can_get_notification_settings(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/notifications/settings');

        $response->assertStatus(200);
        // Migration seeds 6 default notification settings
        $response->assertJsonCount(6);
        $response->assertJsonStructure(['*' => ['key', 'name', 'email_enabled', 'sms_enabled', 'in_app_enabled']]);
    }

    public function test_manager_can_get_notification_settings(): void
    {
        $response = $this->actingAsManager()->getJson('/api/notifications/settings');

        $response->assertStatus(200);
    }

    public function test_cashier_cannot_get_notification_settings(): void
    {
        $response = $this->actingAsCashier()->getJson('/api/notifications/settings');

        $response->assertStatus(403);
    }

    // ---- Update Setting ----

    public function test_owner_can_update_notification_setting(): void
    {
        $this->getOrCreateSetting('low_stock_alert');

        $response = $this->actingAsOwner()->putJson('/api/notifications/settings/low_stock_alert', [
            'email_enabled' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Notification setting updated successfully');
        $this->assertFalse(NotificationSetting::where('key', 'low_stock_alert')->first()->email_enabled);
    }

    public function test_update_nonexistent_setting_returns_404(): void
    {
        $response = $this->actingAsOwner()->putJson('/api/notifications/settings/nonexistent_key', [
            'email_enabled' => false,
        ]);

        $response->assertStatus(404);
    }

    public function test_cashier_cannot_update_notification_settings(): void
    {
        $response = $this->actingAsCashier()->putJson('/api/notifications/settings/low_stock_alert', [
            'email_enabled' => true,
        ]);

        $response->assertStatus(403);
    }

    // ---- Logs ----

    public function test_owner_can_view_notification_logs(): void
    {
        $response = $this->actingAsOwner()->getJson('/api/notifications/logs');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    // ---- Test Notification ----

    public function test_test_notification_requires_type(): void
    {
        $response = $this->actingAsOwner()->postJson('/api/notifications/test', [
            'recipient' => 'test@example.com',
            'message' => 'Test',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    public function test_test_notification_requires_valid_type(): void
    {
        $response = $this->actingAsOwner()->postJson('/api/notifications/test', [
            'type' => 'invalid',
            'recipient' => 'test@example.com',
            'message' => 'Test',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    public function test_test_notification_requires_recipient(): void
    {
        $response = $this->actingAsOwner()->postJson('/api/notifications/test', [
            'type' => 'email',
            'message' => 'Test',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['recipient']);
    }
}