<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---- Show Profile ----

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create(['role' => 'owner', 'is_active' => true]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/profile');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ]);
    }

    public function test_unauthenticated_user_cannot_get_profile(): void
    {
        $response = $this->getJson('/api/profile');

        $response->assertStatus(401);
    }

    // ---- Update Profile ----

    public function test_user_can_update_name(): void
    {
        $user = User::factory()->create(['name' => 'Old Name', 'role' => 'owner', 'is_active' => true]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/profile', [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('New Name', $user->fresh()->name);
    }

    public function test_user_can_update_email(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com', 'role' => 'owner', 'is_active' => true]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/profile', [
            'email' => 'new@example.com',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('new@example.com', $user->fresh()->email);
    }

    public function test_user_cannot_update_to_duplicate_email(): void
    {
        $otherUser = User::factory()->create(['email' => 'taken@example.com']);
        $user = User::factory()->create(['role' => 'owner', 'is_active' => true]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/profile', [
            'email' => 'taken@example.com',
        ]);

        $response->assertStatus(422);
    }

    // ---- Update Password ----

    public function test_user_can_update_password_with_correct_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
            'role' => 'owner',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/profile/password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200);
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_user_cannot_update_password_with_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('correctpassword'),
            'role' => 'owner',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/profile/password', [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422);
    }

    public function test_password_update_requires_confirmation(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
            'role' => 'owner',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')->putJson('/api/profile/password', [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'differentpassword',
        ]);

        $response->assertStatus(422);
    }
}