<?php

namespace Tests\Unit\Models;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_label_with_known_action(): void
    {
        $log = AuditLog::factory()->create(['action' => 'sale.create']);

        $this->assertEquals('Created Sale', $log->action_label);
    }

    public function test_action_label_with_null_action(): void
    {
        $log = AuditLog::factory()->create(['action' => null]);

        $this->assertEquals('Unknown Action', $log->action_label);
    }

    public function test_action_label_with_unknown_action_falls_back(): void
    {
        $log = AuditLog::factory()->create(['action' => 'custom.action']);

        $this->assertEquals('Action Custom', $log->action_label);
    }

    public function test_model_label_with_known_model(): void
    {
        $log = AuditLog::factory()->create(['model_type' => Product::class]);

        $this->assertEquals('Product', $log->model_label);
    }

    public function test_model_label_with_null_model_type(): void
    {
        $log = AuditLog::factory()->create(['model_type' => null]);

        $this->assertEquals('System', $log->model_label);
    }

    public function test_model_label_with_unknown_model_falls_back(): void
    {
        $log = AuditLog::factory()->create(['model_type' => 'App\Models\CustomThing']);

        $this->assertEquals(' Custom Thing', $log->model_label);
    }

    public function test_action_category_with_known_action(): void
    {
        $log = AuditLog::factory()->create(['action' => 'sale.create']);

        $this->assertEquals('create', $log->action_category);
    }

    public function test_action_category_with_null_action(): void
    {
        $log = AuditLog::factory()->create(['action' => null]);

        $this->assertEquals('other', $log->action_category);
    }

    public function test_action_category_with_unknown_action(): void
    {
        $log = AuditLog::factory()->create(['action' => 'unknown.action']);

        $this->assertEquals('other', $log->action_category);
    }

    public function test_log_creates_audit_entry(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $product = Product::factory()->create();
        $log = AuditLog::log('product.create', $product, null, ['name' => 'Test']);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'action' => 'product.create',
            'model_type' => Product::class,
            'model_id' => $product->id,
            'user_id' => $user->id,
        ]);

        $this->assertEquals(['name' => 'Test'], $log->new_values);
    }

    public function test_log_action_creates_entry(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $log = AuditLog::logAction('auth.login', User::class, $user->id);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'action' => 'auth.login',
            'model_type' => User::class,
            'model_id' => $user->id,
        ]);
    }

    public function test_audit_log_serialization_with_null_action(): void
    {
        $log = AuditLog::factory()->create(['action' => null]);

        // Should not crash when serializing
        $array = $log->toArray();

        $this->assertEquals('Unknown Action', $array['action_label']);
        $this->assertNull($array['action']);
    }

    public function test_audit_log_serialization_with_null_model_type(): void
    {
        $log = AuditLog::factory()->create(['model_type' => null]);

        $array = $log->toArray();

        $this->assertEquals('System', $array['model_label']);
        $this->assertNull($array['model_type']);
    }
}