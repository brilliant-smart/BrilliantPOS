<?php

namespace Tests\Feature\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---- Index (list) ----

    public function test_cashier_can_list_expense_categories(): void
    {
        ExpenseCategory::factory()->count(3)->create(['is_active' => true]);

        $response = $this->actingAsCashier()->getJson('/api/expense-categories');

        $response->assertStatus(200);
        $categories = $response->json();
        $this->assertCount(3, $categories);
    }

    public function test_list_only_returns_active_categories_when_requested(): void
    {
        ExpenseCategory::factory()->count(2)->create(['is_active' => true]);
        ExpenseCategory::factory()->create(['is_active' => false]);

        $response = $this->actingAsCashier()->getJson('/api/expense-categories?active_only=1');

        $response->assertStatus(200);
        $categories = $response->json();
        foreach ($categories as $cat) {
            $this->assertTrue($cat['is_active']);
        }
    }

    // ---- Store (create) ----

    public function test_owner_can_create_expense_category(): void
    {
        $response = $this->actingAsOwner()->postJson('/api/expense-categories', [
            'name' => 'New Category',
            'description' => 'A test category',
            'icon' => 'tag',
            'color' => '#FF0000',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('expense_categories', [
            'name' => 'New Category',
            'color' => '#FF0000',
        ]);
    }

    public function test_manager_can_create_expense_category(): void
    {
        $response = $this->actingAsManager()->postJson('/api/expense-categories', [
            'name' => 'Manager Category',
            'description' => 'Created by manager',
        ]);

        $response->assertStatus(201);
    }

    public function test_cashier_cannot_create_expense_category(): void
    {
        $response = $this->actingAsCashier()->postJson('/api/expense-categories', [
            'name' => 'Cashier Category',
        ]);

        $response->assertStatus(403);
    }

    // ---- Update ----

    public function test_owner_can_update_expense_category(): void
    {
        $category = ExpenseCategory::factory()->create(['name' => 'Original']);

        $response = $this->actingAsOwner()->putJson("/api/expense-categories/{$category->id}", [
            'name' => 'Updated',
            'color' => '#00FF00',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'name' => 'Updated',
        ]);
    }

    // ---- Destroy ----

    public function test_owner_can_delete_category_without_expenses(): void
    {
        $category = ExpenseCategory::factory()->create();

        $response = $this->actingAsOwner()->deleteJson("/api/expense-categories/{$category->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('expense_categories', ['id' => $category->id]);
    }

    public function test_cannot_delete_category_with_expenses(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $category = ExpenseCategory::factory()->create();
        Expense::factory()->create(['category_id' => $category->id, 'recorded_by' => $owner->id]);

        $response = $this->actingAsOwner()->deleteJson("/api/expense-categories/{$category->id}");

        $response->assertStatus(400);
    }

    public function test_cashier_cannot_delete_expense_category(): void
    {
        $category = ExpenseCategory::factory()->create();

        $response = $this->actingAsCashier()->deleteJson("/api/expense-categories/{$category->id}");

        $response->assertStatus(403);
    }
}