<?php

namespace Tests\Feature\Controllers;

use App\Models\AuditLog;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_delete_supplier_without_purchase_orders(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($owner, 'sanctum')->deleteJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    public function test_cannot_delete_supplier_with_existing_purchase_orders(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $supplier = Supplier::factory()->create();
        PurchaseOrder::factory()->create(['supplier_id' => $supplier->id]);

        $response = $this->actingAs($owner, 'sanctum')->deleteJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(422);
        $response->assertJson(['message' => 'Cannot delete supplier with existing purchase orders. Please deactivate instead.']);
    }

    public function test_deleting_supplier_creates_audit_log(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $supplier = Supplier::factory()->create();

        $this->actingAs($owner, 'sanctum')->deleteJson("/api/suppliers/{$supplier->id}");

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'supplier.delete',
        ]);
    }

    public function test_manager_can_delete_supplier(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'is_active' => true]);
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($manager, 'sanctum')->deleteJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(200);
    }

    public function test_cashier_cannot_delete_supplier(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($cashier, 'sanctum')->deleteJson("/api/suppliers/{$supplier->id}");

        $response->assertStatus(403);
    }
}