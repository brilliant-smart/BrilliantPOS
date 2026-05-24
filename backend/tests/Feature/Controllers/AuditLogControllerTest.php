<?php

namespace Tests\Feature\Controllers;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_logs(): void
    {
        $this->actingAsOwner();
        $user = User::factory()->create();
        AuditLog::factory()->count(5)->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/audit-logs');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_index_filters_by_action(): void
    {
        $this->actingAsOwner();
        $user = User::factory()->create();
        AuditLog::factory()->create(['action' => 'product.create', 'user_id' => $user->id]);
        AuditLog::factory()->create(['action' => 'sale.create', 'user_id' => $user->id]);

        $response = $this->getJson('/api/audit-logs?action=product.create');

        $response->assertStatus(200);
        $logs = $response->json('data');
        foreach ($logs as $log) {
            $this->assertEquals('product.create', $log['action']);
        }
    }

    public function test_index_filters_by_action_category(): void
    {
        $this->actingAsOwner();
        $user = User::factory()->create();
        AuditLog::factory()->create(['action' => 'product.create', 'user_id' => $user->id]);
        AuditLog::factory()->create(['action' => 'sale.void', 'user_id' => $user->id]);

        $response = $this->getJson('/api/audit-logs?action_category=create');

        $response->assertStatus(200);
    }

    public function test_index_filters_by_model_type(): void
    {
        $this->actingAsOwner();
        $user = User::factory()->create();
        AuditLog::factory()->create(['model_type' => Product::class, 'user_id' => $user->id]);
        AuditLog::factory()->create(['model_type' => User::class, 'user_id' => $user->id]);

        $response = $this->getJson('/api/audit-logs?model_type=Product');

        $response->assertStatus(200);
    }

    public function test_statistics_endpoint_returns_200(): void
    {
        $this->actingAsOwner();
        $user = User::factory()->create();
        AuditLog::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/audit-logs/statistics');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_actions',
            'today_actions',
            'week_actions',
            'active_users',
            'by_action',
            'by_model',
            'by_user',
            'recent_activities',
        ]);
    }

    public function test_statistics_handles_null_action(): void
    {
        $this->actingAsOwner();
        $user = User::factory()->create();
        AuditLog::factory()->create(['action' => null, 'model_type' => null, 'user_id' => $user->id]);

        $response = $this->getJson('/api/audit-logs/statistics');

        $response->assertStatus(200);
    }
}