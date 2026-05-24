<?php

namespace Tests\Feature\Controllers;

use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---- List Webhooks ----

    public function test_owner_can_list_webhooks(): void
    {
        Webhook::create([
            'name' => 'Test Hook',
            'url' => 'https://example.com/webhook',
            'events' => ['sale.created'],
            'secret' => 'secret123',
            'is_active' => true,
        ]);

        $response = $this->actingAsOwner()->getJson('/api/webhooks');

        $response->assertStatus(200);
        $webhooks = $response->json();
        $this->assertCount(1, $webhooks);
        // Secret should be masked — the raw secret should not appear
        $this->assertStringNotContainsString('secret123', json_encode($webhooks));
        // Name should be present in the response
        $this->assertEquals('Test Hook', $webhooks[0]['name']);
    }

    public function test_cashier_cannot_list_webhooks(): void
    {
        $response = $this->actingAsCashier()->getJson('/api/webhooks');

        $response->assertStatus(403);
    }

    // ---- Create Webhook ----

    public function test_owner_can_create_webhook(): void
    {
        $response = $this->actingAsOwner()->postJson('/api/webhooks', [
            'name' => 'Test Webhook',
            'url' => 'https://example.com/webhook',
            'events' => ['sale.created', 'sale.voided'],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('webhooks', [
            'name' => 'Test Webhook',
            'url' => 'https://example.com/webhook',
            'is_active' => true,
        ]);
        // Secret should be auto-generated
        $webhook = Webhook::first();
        $this->assertNotEmpty($webhook->secret);
    }

    public function test_webhook_creation_auto_generates_secret(): void
    {
        $this->actingAsOwner()->postJson('/api/webhooks', [
            'name' => 'Auto Secret Hook',
            'url' => 'https://example.com/hook',
            'events' => ['inventory.low_stock'],
        ]);

        $webhook = Webhook::first();
        $this->assertNotNull($webhook->secret);
        $this->assertEquals(32, strlen($webhook->secret)); // 16 bytes = 32 hex chars
    }

    public function test_webhook_creation_with_custom_secret(): void
    {
        $this->actingAsOwner()->postJson('/api/webhooks', [
            'name' => 'Custom Secret Hook',
            'url' => 'https://example.com/hook',
            'events' => ['sale.created'],
            'secret' => 'my-custom-secret-key',
        ]);

        $this->assertDatabaseHas('webhooks', [
            'name' => 'Custom Secret Hook',
            'secret' => 'my-custom-secret-key',
        ]);
    }

    public function test_webhook_creation_requires_name(): void
    {
        $response = $this->actingAsOwner()->postJson('/api/webhooks', [
            'url' => 'https://example.com/hook',
            'events' => ['sale.created'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_webhook_creation_requires_valid_url(): void
    {
        $response = $this->actingAsOwner()->postJson('/api/webhooks', [
            'name' => 'Bad URL Hook',
            'url' => 'not-a-url',
            'events' => ['sale.created'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['url']);
    }

    public function test_webhook_creation_requires_events_array(): void
    {
        $response = $this->actingAsOwner()->postJson('/api/webhooks', [
            'name' => 'No Events Hook',
            'url' => 'https://example.com/hook',
            'events' => 'not-an-array',
        ]);

        $response->assertStatus(422);
    }

    public function test_webhook_blocks_localhost_url(): void
    {
        $response = $this->actingAsOwner()->postJson('/api/webhooks', [
            'name' => 'Localhost Hook',
            'url' => 'http://localhost/webhook',
            'events' => ['sale.created'],
        ]);

        $response->assertStatus(422);
    }

    public function test_webhook_blocks_private_ip_url(): void
    {
        $response = $this->actingAsOwner()->postJson('/api/webhooks', [
            'name' => 'Private IP Hook',
            'url' => 'http://192.168.1.1/webhook',
            'events' => ['sale.created'],
        ]);

        $response->assertStatus(422);
    }

    public function test_webhook_blocks_127_ip_url(): void
    {
        $response = $this->actingAsOwner()->postJson('/api/webhooks', [
            'name' => 'Loopback Hook',
            'url' => 'http://127.0.0.1/webhook',
            'events' => ['sale.created'],
        ]);

        $response->assertStatus(422);
    }

    public function test_webhook_creation_invalid_event_is_rejected(): void
    {
        $response = $this->actingAsOwner()->postJson('/api/webhooks', [
            'name' => 'Invalid Event Hook',
            'url' => 'https://example.com/hook',
            'events' => ['invalid.event'],
        ]);

        $response->assertStatus(422);
    }

    // ---- Delete Webhook ----

    public function test_owner_can_delete_webhook(): void
    {
        $webhook = Webhook::create([
            'name' => 'Delete Me',
            'url' => 'https://example.com/hook',
            'events' => ['sale.created'],
            'secret' => 'secret',
            'is_active' => true,
        ]);

        $response = $this->actingAsOwner()->deleteJson("/api/webhooks/{$webhook->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('webhooks', ['id' => $webhook->id]);
    }

    public function test_cashier_cannot_delete_webhook(): void
    {
        $webhook = Webhook::create([
            'name' => 'Protected Hook',
            'url' => 'https://example.com/hook',
            'events' => ['sale.created'],
            'secret' => 'secret',
            'is_active' => true,
        ]);

        $response = $this->actingAsCashier()->deleteJson("/api/webhooks/{$webhook->id}");

        $response->assertStatus(403);
    }
}