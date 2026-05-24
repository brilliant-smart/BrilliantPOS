<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---- Index (list users) ----

    public function test_owner_can_list_users(): void
    {
        User::factory()->count(3)->create(['role' => 'cashier']);

        $response = $this->actingAsOwner()->getJson('/api/admin/users');

        $response->assertStatus(200);
    }

    public function test_manager_cannot_list_users(): void
    {
        $response = $this->actingAsManager()->getJson('/api/admin/users');

        $response->assertStatus(403);
    }

    public function test_cashier_cannot_list_users(): void
    {
        $response = $this->actingAsCashier()->getJson('/api/admin/users');

        $response->assertStatus(403);
    }

    // ---- Store (create user) ----

    public function test_owner_can_create_user(): void
    {
        $response = $this->actingAsOwner()->postJson('/api/admin/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'securepassword123',
            'role' => 'cashier',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'role' => 'cashier',
        ]);
    }

    public function test_create_user_hashes_password(): void
    {
        $this->actingAsOwner()->postJson('/api/admin/users', [
            'name' => 'Hash Test',
            'email' => 'hashtest@example.com',
            'password' => 'plaintext123',
            'role' => 'cashier',
        ]);

        $user = User::where('email', 'hashtest@example.com')->first();
        $this->assertNotEquals('plaintext123', $user->password);
        $this->assertTrue(\Hash::check('plaintext123', $user->password));
    }

    public function test_cannot_create_user_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->actingAsOwner()->postJson('/api/admin/users', [
            'name' => 'Duplicate',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'role' => 'cashier',
        ]);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_create_user(): void
    {
        $response = $this->actingAsManager()->postJson('/api/admin/users', [
            'name' => 'Unauthorized',
            'email' => 'unauth@example.com',
            'password' => 'password123',
            'role' => 'cashier',
        ]);

        $response->assertStatus(403);
    }

    // ---- Update ----

    public function test_owner_can_update_user_role(): void
    {
        $targetUser = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $response = $this->actingAsOwner()->patchJson("/api/admin/users/{$targetUser->id}", [
            'role' => 'manager',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('manager', $targetUser->fresh()->role);
    }

    public function test_owner_can_deactivate_user(): void
    {
        $targetUser = User::factory()->create(['is_active' => true]);

        $response = $this->actingAsOwner()->patchJson("/api/admin/users/{$targetUser->id}", [
            'is_active' => false,
        ]);

        $response->assertStatus(200);
        $this->assertFalse($targetUser->fresh()->is_active);
    }

    // ---- Force Delete (anonymization) ----

    public function test_owner_can_force_delete_user(): void
    {
        $targetUser = User::factory()->create(['name' => 'Target User', 'email' => 'target@example.com']);

        $response = $this->actingAsOwner()->deleteJson("/api/admin/users/{$targetUser->id}/force");

        $response->assertStatus(200);
        // Force delete anonymizes: name becomes "Deleted User", email changes, is_active becomes false
        $freshUser = $targetUser->fresh();
        $this->assertEquals('Deleted User', $freshUser->name);
        $this->assertFalse($freshUser->is_active);
        $this->assertNotEquals('target@example.com', $freshUser->email);
    }

    public function test_manager_cannot_force_delete_user(): void
    {
        $targetUser = User::factory()->create();

        $response = $this->actingAsManager()->deleteJson("/api/admin/users/{$targetUser->id}/force");

        $response->assertStatus(403);
    }
}