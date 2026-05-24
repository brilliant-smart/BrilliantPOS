<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\AuthenticateWithToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticateWithTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register a test route protected by the middleware
        Route::get('/test-token-auth', function (\Illuminate\Http\Request $request) {
            return response()->json([
                'user_id' => $request->user()?->id,
                'authenticated' => $request->user() !== null,
            ]);
        })->middleware(\App\Http\Middleware\AuthenticateWithToken::class);
    }

    // ---- Token-based authentication ----

    public function test_valid_token_authenticates_user(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = $user->createToken('test-token');
        $plainTextToken = $token->plainTextToken;

        $response = $this->getJson("/test-token-auth?token={$plainTextToken}");

        $response->assertStatus(200);
        $response->assertJsonPath('authenticated', true);
        $response->assertJsonPath('user_id', $user->id);
    }

    public function test_bearer_token_authenticates_user(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = $user->createToken('test-token');
        $plainTextToken = $token->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $plainTextToken,
        ])->getJson('/test-token-auth');

        $response->assertStatus(200);
        $response->assertJsonPath('authenticated', true);
    }

    public function test_invalid_token_returns_401(): void
    {
        $response = $this->getJson('/test-token-auth?token=invalid-token-12345');

        $response->assertStatus(401);
    }

    public function test_missing_token_and_session_returns_401(): void
    {
        $response = $this->getJson('/test-token-auth');

        $response->assertStatus(401);
    }

    // ---- Session-based authentication ----

    public function test_session_authenticated_user_passes(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($user)->getJson('/test-token-auth');

        $response->assertStatus(200);
        $response->assertJsonPath('authenticated', true);
        $response->assertJsonPath('user_id', $user->id);
    }

    // ---- Token priority ----

    public function test_token_takes_priority_over_session(): void
    {
        $user1 = User::factory()->create(['is_active' => true]);
        $user2 = User::factory()->create(['is_active' => true]);
        $token = $user2->createToken('test-token');
        $plainTextToken = $token->plainTextToken;

        // Logged in as user1 but providing token for user2
        $response = $this->actingAs($user1)->getJson("/test-token-auth?token={$plainTextToken}");

        $response->assertStatus(200);
        // Token user (user2) should take priority
        $response->assertJsonPath('user_id', $user2->id);
    }
}