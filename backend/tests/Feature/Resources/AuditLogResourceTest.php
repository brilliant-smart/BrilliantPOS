<?php

namespace Tests\Feature\Resources;

use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_with_null_action(): void
    {
        $log = AuditLog::factory()->create(['action' => null]);

        $resource = new AuditLogResource($log);
        $array = $resource->resolve();

        // Should not crash — action_label should use when() conditional
        $this->assertNull($array['action']);
        // action_label should not be present (when condition false)
    }

    public function test_resource_with_null_model_type(): void
    {
        $log = AuditLog::factory()->create(['model_type' => null]);

        $resource = new AuditLogResource($log);
        $array = $resource->resolve();

        $this->assertNull($array['model_type']);
        // model_label should not be present (when condition false)
    }

    public function test_resource_with_known_action(): void
    {
        $log = AuditLog::factory()->create(['action' => 'sale.create']);

        $resource = new AuditLogResource($log);
        $array = $resource->resolve();

        $this->assertEquals('sale.create', $array['action']);
        $this->assertEquals('Created Sale', $array['action_label']);
    }

    public function test_resource_with_known_model(): void
    {
        $log = AuditLog::factory()->create(['model_type' => Product::class]);

        $resource = new AuditLogResource($log);
        $array = $resource->resolve();

        $this->assertEquals(Product::class, $array['model_type']);
        $this->assertEquals('Product', $array['model_label']);
    }

    public function test_resource_includes_user_relation(): void
    {
        $user = User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com', 'role' => 'owner']);
        $log = AuditLog::factory()->create(['user_id' => $user->id]);
        $log->load('user');

        $resource = new AuditLogResource($log);
        $array = $resource->resolve();

        $this->assertArrayHasKey('user', $array);
        $this->assertEquals('Test User', $array['user']['name']);
        $this->assertEquals('owner', $array['user']['role']);
    }

    public function test_resource_without_loaded_user(): void
    {
        $log = AuditLog::factory()->create();

        $resource = new AuditLogResource($log);
        $array = $resource->resolve();

        // whenLoaded should omit user when not loaded
        $this->assertArrayNotHasKey('user', $array);
    }
}