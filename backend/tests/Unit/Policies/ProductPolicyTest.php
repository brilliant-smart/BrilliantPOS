<?php

namespace Tests\Unit\Policies;

use App\Models\Product;
use App\Models\User;
use App\Policies\ProductPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ProductPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ProductPolicy();
    }

    // ---- view ----

    public function test_owner_can_view_product(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $product = Product::factory()->create();

        $this->assertTrue($this->policy->view($owner, $product));
    }

    public function test_manager_can_view_product(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $product = Product::factory()->create();

        $this->assertTrue($this->policy->view($manager, $product));
    }

    public function test_cashier_cannot_view_product(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $product = Product::factory()->create();

        $this->assertFalse($this->policy->view($cashier, $product));
    }

    // ---- create ----

    public function test_owner_can_create_product(): void
    {
        $this->assertTrue($this->policy->create(User::factory()->create(['role' => 'owner'])));
    }

    public function test_manager_can_create_product(): void
    {
        $this->assertTrue($this->policy->create(User::factory()->create(['role' => 'manager'])));
    }

    public function test_cashier_cannot_create_product(): void
    {
        $this->assertFalse($this->policy->create(User::factory()->create(['role' => 'cashier'])));
    }

    // ---- update ----

    public function test_owner_can_update_product(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $product = Product::factory()->create();

        $this->assertTrue($this->policy->update($owner, $product));
    }

    public function test_manager_can_update_product(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $product = Product::factory()->create();

        $this->assertTrue($this->policy->update($manager, $product));
    }

    public function test_cashier_cannot_update_product(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $product = Product::factory()->create();

        $this->assertFalse($this->policy->update($cashier, $product));
    }

    // ---- delete ----

    public function test_owner_can_delete_product(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $product = Product::factory()->create();

        $this->assertTrue($this->policy->delete($owner, $product));
    }

    public function test_manager_can_delete_product(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $product = Product::factory()->create();

        $this->assertTrue($this->policy->delete($manager, $product));
    }

    public function test_cashier_cannot_delete_product(): void
    {
        $cashier = User::factory()->create(['role' => 'cashier']);
        $product = Product::factory()->create();

        $this->assertFalse($this->policy->delete($cashier, $product));
    }
}