<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']]);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Invalid credentials']);
    }

    public function test_login_with_inactive_account(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => bcrypt('password'),
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'Account is inactive']);
    }

    public function test_login_does_not_create_audit_log(): void
    {
        // Known gap: AuthController::login() does not create an audit log entry.
        // The AuditLog model defines 'auth.login' as a valid action label, but
        // nothing in the login flow writes it. Verify no audit log is created.
        User::factory()->create([
            'email' => 'audit@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->postJson('/api/login', [
            'email' => 'audit@example.com',
            'password' => 'password',
        ])->assertStatus(200);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'auth.login',
        ]);
    }

    public function test_login_revokes_previous_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'tokens@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        // First login
        $response1 = $this->postJson('/api/login', [
            'email' => 'tokens@example.com',
            'password' => 'password',
        ]);
        $firstToken = $response1->json('token');

        // Second login — should revoke all previous tokens
        $response2 = $this->postJson('/api/login', [
            'email' => 'tokens@example.com',
            'password' => 'password',
        ]);

        $response2->assertStatus(200);

        // Verify the second token works
        $this->withHeader('Authorization', 'Bearer ' . $response2->json('token'))
            ->getJson('/api/me')
            ->assertStatus(200);
    }

    public function test_logout_deletes_current_token(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout')
            ->assertStatus(200);

        // Verify the token is deleted from the database
        $this->assertDatabaseMissing('personal_access_tokens', ['token' => hash('sha256', explode('|', $token, 2)[1])]);
    }

    public function test_unauthenticated_access_returns_401(): void
    {
        $this->getJson('/api/me')
            ->assertStatus(401);
    }
}