<?php

namespace Tests\Unit\Policies;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Policies\PurchaseOrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderPolicyTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrderPolicy $policy;
    private PurchaseOrder $po;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new PurchaseOrderPolicy();
        $supplier = Supplier::factory()->create();
        $owner = User::factory()->create(['role' => 'owner']);
        $this->po = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'created_by' => $owner->id,
        ]);
    }

    // ---- viewAny ----

    public function test_owner_can_view_any_po(): void
    {
        $this->assertTrue($this->policy->viewAny(User::factory()->create(['role' => 'owner'])));
    }

    public function test_manager_can_view_any_po(): void
    {
        $this->assertTrue($this->policy->viewAny(User::factory()->create(['role' => 'manager'])));
    }

    public function test_cashier_can_view_any_po(): void
    {
        $this->assertTrue($this->policy->viewAny(User::factory()->create(['role' => 'cashier'])));
    }

    // ---- view ----

    public function test_all_roles_can_view_po(): void
    {
        $this->assertTrue($this->policy->view(User::factory()->create(['role' => 'owner']), $this->po));
        $this->assertTrue($this->policy->view(User::factory()->create(['role' => 'manager']), $this->po));
        $this->assertTrue($this->policy->view(User::factory()->create(['role' => 'cashier']), $this->po));
    }

    // ---- create ----

    public function test_all_roles_can_create_po(): void
    {
        $this->assertTrue($this->policy->create(User::factory()->create(['role' => 'owner'])));
        $this->assertTrue($this->policy->create(User::factory()->create(['role' => 'manager'])));
        $this->assertTrue($this->policy->create(User::factory()->create(['role' => 'cashier'])));
    }

    // ---- update ----

    public function test_owner_can_update_po(): void
    {
        $this->assertTrue($this->policy->update(User::factory()->create(['role' => 'owner']), $this->po));
    }

    public function test_manager_can_update_po(): void
    {
        $this->assertTrue($this->policy->update(User::factory()->create(['role' => 'manager']), $this->po));
    }

    public function test_cashier_cannot_update_po(): void
    {
        $this->assertFalse($this->policy->update(User::factory()->create(['role' => 'cashier']), $this->po));
    }

    // ---- approve ----

    public function test_owner_can_approve_po(): void
    {
        $this->assertTrue($this->policy->approve(User::factory()->create(['role' => 'owner']), $this->po));
    }

    public function test_manager_can_approve_po(): void
    {
        $this->assertTrue($this->policy->approve(User::factory()->create(['role' => 'manager']), $this->po));
    }

    public function test_cashier_cannot_approve_po(): void
    {
        $this->assertFalse($this->policy->approve(User::factory()->create(['role' => 'cashier']), $this->po));
    }

    // ---- reject ----

    public function test_cashier_cannot_reject_po(): void
    {
        $this->assertFalse($this->policy->reject(User::factory()->create(['role' => 'cashier']), $this->po));
    }

    // ---- cancel ----

    public function test_cashier_cannot_cancel_po(): void
    {
        $this->assertFalse($this->policy->cancel(User::factory()->create(['role' => 'cashier']), $this->po));
    }

    // ---- receiveGoods ----

    public function test_all_roles_can_receive_goods(): void
    {
        $this->assertTrue($this->policy->receiveGoods(User::factory()->create(['role' => 'owner']), $this->po));
        $this->assertTrue($this->policy->receiveGoods(User::factory()->create(['role' => 'manager']), $this->po));
        $this->assertTrue($this->policy->receiveGoods(User::factory()->create(['role' => 'cashier']), $this->po));
    }

    // ---- recordPayment ----

    public function test_all_roles_can_record_payment(): void
    {
        $this->assertTrue($this->policy->recordPayment(User::factory()->create(['role' => 'owner']), $this->po));
        $this->assertTrue($this->policy->recordPayment(User::factory()->create(['role' => 'manager']), $this->po));
        $this->assertTrue($this->policy->recordPayment(User::factory()->create(['role' => 'cashier']), $this->po));
    }

    // ---- delete ----

    public function test_cashier_cannot_delete_po(): void
    {
        $this->assertFalse($this->policy->delete(User::factory()->create(['role' => 'cashier']), $this->po));
    }

    // ---- export ----

    public function test_all_roles_can_export_po(): void
    {
        $this->assertTrue($this->policy->export(User::factory()->create(['role' => 'owner']), $this->po));
        $this->assertTrue($this->policy->export(User::factory()->create(['role' => 'manager']), $this->po));
        $this->assertTrue($this->policy->export(User::factory()->create(['role' => 'cashier']), $this->po));
    }
}