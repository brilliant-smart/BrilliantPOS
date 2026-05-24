<?php

namespace Tests\Feature\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---- index ----

    public function test_owner_can_list_users(): void
    {
        User::factory()->create(['role' => 'owner', 'is_active' => true]);
        User::factory()->create(['role' => 'manager', 'is_active' => true]);
        User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $response = $this->actingAsOwner()->getJson('/api/admin/users');

        $response->assertStatus(200);
        $response->assertJsonCount(4); // 3 created + actingAsOwner
        $response->assertJsonStructure(['*' => ['id', 'name', 'email', 'role', 'is_active']]);
    }

    public function test_list_excludes_deleted_users(): void
    {
        User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        User::factory()->create(['name' => 'Deleted User', 'role' => 'cashier', 'is_active' => false]);

        $response = $this->actingAsOwner()->getJson('/api/admin/users');

        $response->assertStatus(200);
        $names = collect($response->json())->pluck('name');
        $this->assertFalse($names->contains('Deleted User'));
    }

    public function test_manager_cannot_list_users(): void
    {
        $this->actingAsManager()->getJson('/api/admin/users')->assertStatus(403);
    }

    public function test_cashier_cannot_list_users(): void
    {
        $this->actingAsCashier()->getJson('/api/admin/users')->assertStatus(403);
    }

    // ---- store ----

    public function test_owner_can_create_user(): void
    {
        $response = $this->actingAsOwner()->postJson('/api/admin/users', [
            'name' => 'New Manager',
            'email' => 'manager@example.com',
            'password' => 'securepass123',
            'role' => 'manager',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('name', 'New Manager');
        $response->assertJsonPath('email', 'manager@example.com');
        $response->assertJsonPath('role', 'manager');

        $this->assertDatabaseHas('users', ['email' => 'manager@example.com', 'role' => 'manager']);
    }

    public function test_create_user_hashes_password(): void
    {
        $this->actingAsOwner()->postJson('/api/admin/users', [
            'name' => 'Hash Test',
            'email' => 'hash@example.com',
            'password' => 'plaintext123',
            'role' => 'cashier',
        ]);

        $user = User::where('email', 'hash@example.com')->first();
        $this->assertNotEquals('plaintext123', $user->password);
        $this->assertTrue(password_verify('plaintext123', $user->password));
    }

    public function test_create_user_logs_audit(): void
    {
        $this->actingAsOwner()->postJson('/api/admin/users', [
            'name' => 'Audit User',
            'email' => 'audit@example.com',
            'password' => 'password123',
            'role' => 'cashier',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'user.create']);
    }

    public function test_create_user_validates_required_fields(): void
    {
        $this->actingAsOwner()->postJson('/api/admin/users', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    }

    public function test_create_user_validates_email_uniqueness(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAsOwner()->postJson('/api/admin/users', [
            'name' => 'Duplicate',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'role' => 'cashier',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['email']);
    }

    public function test_create_user_validates_role(): void
    {
        $this->actingAsOwner()->postJson('/api/admin/users', [
            'name' => 'Bad Role',
            'email' => 'badrole@example.com',
            'password' => 'password123',
            'role' => 'superadmin',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['role']);
    }

    public function test_create_user_validates_password_length(): void
    {
        $this->actingAsOwner()->postJson('/api/admin/users', [
            'name' => 'Short Pass',
            'email' => 'shortpass@example.com',
            'password' => '1234567',
            'role' => 'cashier',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['password']);
    }

    public function test_manager_cannot_create_user(): void
    {
        $this->actingAsManager()->postJson('/api/admin/users', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'password123',
            'role' => 'cashier',
        ])->assertStatus(403);
    }

    // ---- update ----

    public function test_owner_can_update_user(): void
    {
        $user = User::factory()->create(['role' => 'cashier', 'is_active' => true, 'name' => 'Old Name']);

        $response = $this->actingAsOwner()->patchJson("/api/admin/users/{$user->id}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $user->fresh()->name);
    }

    public function test_update_can_change_role(): void
    {
        $user = User::factory()->create(['role' => 'cashier', 'is_active' => true]);

        $this->actingAsOwner()->patchJson("/api/admin/users/{$user->id}", [
            'role' => 'manager',
        ]);

        $this->assertEquals('manager', $user->fresh()->role);
    }

    public function test_update_can_toggle_active_status(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAsOwner()->patchJson("/api/admin/users/{$user->id}", [
            'is_active' => false,
        ]);

        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_update_hashes_new_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('oldpass')]);

        $this->actingAsOwner()->patchJson("/api/admin/users/{$user->id}", [
            'password' => 'newpass123',
        ]);

        $this->assertTrue(password_verify('newpass123', $user->fresh()->password));
    }

    public function test_update_does_not_change_password_when_omitted(): void
    {
        $user = User::factory()->create(['name' => 'Keep Pass', 'password' => bcrypt('original123')]);
        $originalHash = $user->password;

        $this->actingAsOwner()->patchJson("/api/admin/users/{$user->id}", [
            'name' => 'New Name',
        ]);

        $this->assertEquals($originalHash, $user->fresh()->password);
    }

    public function test_update_validates_email_uniqueness_excluding_self(): void
    {
        $user = User::factory()->create(['email' => 'me@example.com']);

        // Updating own email to the same address should work
        $this->actingAsOwner()->patchJson("/api/admin/users/{$user->id}", [
            'email' => 'me@example.com',
        ])->assertStatus(200);
    }

    public function test_update_logs_audit(): void
    {
        $user = User::factory()->create(['role' => 'cashier']);

        $this->actingAsOwner()->patchJson("/api/admin/users/{$user->id}", [
            'name' => 'Updated Name',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'user.update']);
    }

    public function test_manager_cannot_update_user(): void
    {
        $user = User::factory()->create(['role' => 'cashier']);

        $this->actingAsManager()->patchJson("/api/admin/users/{$user->id}", [
            'name' => 'Hacked',
        ])->assertStatus(403);
    }

    // ---- destroy (soft deactivate) ----

    public function test_owner_can_deactivate_user(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAsOwner()->deleteJson("/api/admin/users/{$user->id}");

        $response->assertStatus(200);
        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_deactivate_creates_audit_log(): void
    {
        $user = User::factory()->create(['name' => 'Target User']);

        $this->actingAsOwner()->deleteJson("/api/admin/users/{$user->id}");

        $this->assertDatabaseHas('audit_logs', ['action' => 'user.deactivate']);
    }

    public function test_owner_cannot_deactivate_self(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/admin/users/{$owner->id}")
            ->assertStatus(422);

        $this->assertTrue($owner->fresh()->is_active);
    }

    // ---- forceDelete (anonymize) ----

    public function test_owner_can_permanently_delete_user(): void
    {
        $user = User::factory()->create(['name' => 'Real Name', 'email' => 'real@example.com', 'is_active' => true]);

        $response = $this->actingAsOwner()->deleteJson("/api/admin/users/{$user->id}/force");

        $response->assertStatus(200);
        $fresh = $user->fresh();
        $this->assertEquals('Deleted User', $fresh->name);
        $this->assertFalse($fresh->is_active);
        $this->assertStringContainsString('deleted_', $fresh->email);
    }

    public function test_force_delete_revokes_tokens(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = $user->createToken('test')->plainTextToken;

        $this->actingAsOwner()->deleteJson("/api/admin/users/{$user->id}/force");

        // Token should be deleted
        $this->assertEquals(0, $user->fresh()->tokens()->count());
    }

    public function test_force_delete_creates_audit_log(): void
    {
        $user = User::factory()->create(['name' => 'Gone User']);

        $this->actingAsOwner()->deleteJson("/api/admin/users/{$user->id}/force");

        $this->assertDatabaseHas('audit_logs', ['action' => 'user.delete']);
    }

    public function test_owner_cannot_force_delete_self(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/admin/users/{$owner->id}/force")
            ->assertStatus(422);

        $this->assertNotEquals('Deleted User', $owner->fresh()->name);
    }

    public function test_manager_cannot_force_delete_user(): void
    {
        $user = User::factory()->create(['role' => 'cashier']);

        $this->actingAsManager()->deleteJson("/api/admin/users/{$user->id}/force")->assertStatus(403);
    }
}