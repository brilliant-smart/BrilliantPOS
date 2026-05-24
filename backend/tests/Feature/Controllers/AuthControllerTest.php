<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---- Login ----

    public function test_user_can_login_with_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'inactive@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
    }

    public function test_login_enforces_single_session(): void
    {
        $user = User::factory()->create([
            'email' => 'single@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        // First login
        $response1 = $this->postJson('/api/login', [
            'email' => 'single@example.com',
            'password' => 'password123',
        ]);
        $token1 = $response1->json('token');
        $this->assertNotEmpty($token1);

        // Second login should invalidate the first token
        $response2 = $this->postJson('/api/login', [
            'email' => 'single@example.com',
            'password' => 'password123',
        ]);
        $token2 = $response2->json('token');
        $this->assertNotEmpty($token2);
        $this->assertNotEquals($token1, $token2);

        // Verify old token was deleted from database
        $this->assertEquals(1, $user->fresh()->tokens()->count());
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'auth_token',
        ]);
        // The new token should exist
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_login_requires_email(): void
    {
        $response = $this->postJson('/api/login', [
            'password' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_login_requires_password(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    // ---- Logout ----

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        // Create a real Sanctum token directly
        $token = $user->createToken('test-token')->plainTextToken;

        // Use the real Bearer token for logout (not actingAs which uses TransientToken)
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/logout');

        $response->assertStatus(200);
        $this->assertEquals(0, $user->fresh()->tokens()->count());
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertStatus(401);
    }

    // ---- Forgot Password ----

    public function test_forgot_password_returns_generic_response(): void
    {
        $response = $this->postJson('/api/forgot-password', [
            'email' => 'test@example.com',
        ]);

        // Always returns 200 to prevent email enumeration
        $response->assertStatus(200);
    }

    public function test_forgot_password_requires_email(): void
    {
        $response = $this->postJson('/api/forgot-password', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }
}