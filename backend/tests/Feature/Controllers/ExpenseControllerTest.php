<?php

namespace Tests\Feature\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createExpenseCategory(): ExpenseCategory
    {
        return ExpenseCategory::factory()->create(['is_active' => true]);
    }

    // ---- Index ----

    public function test_cashier_can_list_expenses(): void
    {
        $category = $this->createExpenseCategory();
        $user = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        Expense::factory()->create(['category_id' => $category->id, 'recorded_by' => $user->id]);

        $response = $this->actingAsCashier()->getJson('/api/expenses');

        $response->assertStatus(200);
    }

    // ---- Store ----

    public function test_cashier_can_create_expense(): void
    {
        $category = $this->createExpenseCategory();

        $response = $this->actingAsCashier()->postJson('/api/expenses', [
            'title' => 'Office Supplies',
            'amount' => 250.00,
            'payment_method' => 'cash',
            'category_id' => $category->id,
            'expense_date' => now()->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('expenses', [
            'title' => 'Office Supplies',
            'amount' => 250.00,
        ]);
    }

    public function test_expense_creation_validates_payment_method(): void
    {
        $category = $this->createExpenseCategory();

        $response = $this->actingAsCashier()->postJson('/api/expenses', [
            'title' => 'Bad Payment',
            'amount' => 100.00,
            'payment_method' => 'invalid_method',
            'category_id' => $category->id,
            'expense_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['payment_method']);
    }

    public function test_expense_creation_requires_amount(): void
    {
        $response = $this->actingAsCashier()->postJson('/api/expenses', [
            'title' => 'No Amount',
            'payment_method' => 'cash',
            'expense_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    }

    public function test_expense_future_date_rejected(): void
    {
        $category = $this->createExpenseCategory();

        $response = $this->actingAsCashier()->postJson('/api/expenses', [
            'title' => 'Future Expense',
            'amount' => 100.00,
            'payment_method' => 'cash',
            'category_id' => $category->id,
            'expense_date' => now()->addDay()->toDateString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['expense_date']);
    }

    public function test_expense_auto_generates_expense_number(): void
    {
        $category = $this->createExpenseCategory();

        $this->actingAsCashier()->postJson('/api/expenses', [
            'title' => 'Auto Number',
            'amount' => 50.00,
            'payment_method' => 'cash',
            'category_id' => $category->id,
            'expense_date' => now()->toDateString(),
        ]);

        $expense = Expense::first();
        $this->assertNotNull($expense->expense_number);
        $this->assertStringStartsWith('EXP-', $expense->expense_number);
    }

    // ---- Show ----

    public function test_cashier_can_view_own_expense(): void
    {
        $category = $this->createExpenseCategory();
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $expense = Expense::factory()->create(['category_id' => $category->id, 'recorded_by' => $cashier->id]);

        $response = $this->actingAs($cashier, 'sanctum')->getJson("/api/expenses/{$expense->id}");

        $response->assertStatus(200);
    }

    // ---- Update ----

    public function test_cashier_can_update_own_expense(): void
    {
        $category = $this->createExpenseCategory();
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $expense = Expense::factory()->create([
            'category_id' => $category->id,
            'recorded_by' => $cashier->id,
            'title' => 'Original Title',
        ]);

        $response = $this->actingAs($cashier, 'sanctum')->putJson("/api/expenses/{$expense->id}", [
            'title' => 'Updated Title',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated Title', $expense->fresh()->title);
    }

    public function test_cashier_cannot_update_other_cashier_expense(): void
    {
        $category = $this->createExpenseCategory();
        $otherCashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $expense = Expense::factory()->create([
            'category_id' => $category->id,
            'recorded_by' => $otherCashier->id,
        ]);

        $response = $this->actingAsCashier()->putJson("/api/expenses/{$expense->id}", [
            'title' => 'Hacked Title',
        ]);

        $response->assertStatus(403);
    }

    public function test_owner_can_update_any_expense(): void
    {
        $category = $this->createExpenseCategory();
        $otherUser = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $expense = Expense::factory()->create([
            'category_id' => $category->id,
            'recorded_by' => $otherUser->id,
            'title' => 'Original',
        ]);

        $response = $this->actingAsOwner()->putJson("/api/expenses/{$expense->id}", [
            'title' => 'Owner Updated',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Owner Updated', $expense->fresh()->title);
    }

    // ---- Destroy ----

    public function test_cashier_can_delete_own_expense(): void
    {
        $category = $this->createExpenseCategory();
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $expense = Expense::factory()->create(['category_id' => $category->id, 'recorded_by' => $cashier->id]);

        $response = $this->actingAs($cashier, 'sanctum')->deleteJson("/api/expenses/{$expense->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
    }

    public function test_cashier_cannot_delete_other_cashier_expense(): void
    {
        $category = $this->createExpenseCategory();
        $otherCashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $expense = Expense::factory()->create(['category_id' => $category->id, 'recorded_by' => $otherCashier->id]);

        $response = $this->actingAsCashier()->deleteJson("/api/expenses/{$expense->id}");

        $response->assertStatus(403);
    }

    // ---- Analytics ----

    public function test_cashier_can_view_expense_analytics(): void
    {
        $category = $this->createExpenseCategory();
        $user = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        Expense::factory()->count(3)->create([
            'category_id' => $category->id,
            'recorded_by' => $user->id,
            'expense_date' => now()->toDateString(),
        ]);

        $response = $this->actingAsCashier()->getJson('/api/expenses/analytics');

        $response->assertStatus(200);
        $response->assertJsonStructure(['summary', 'payment_breakdown', 'category_breakdown']);
    }
}