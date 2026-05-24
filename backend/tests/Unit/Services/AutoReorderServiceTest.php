<?php

namespace Tests\Unit\Services;

use App\Models\AutoReorderLog;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\AutoReorderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoReorderServiceTest extends TestCase
{
    use RefreshDatabase;

    private AutoReorderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AutoReorderService::class);
    }

    // ---- getReorderSuggestions ----

    public function test_suggestions_includes_low_stock_products(): void
    {
        $supplier = Supplier::factory()->create(['is_active' => true]);
        Product::factory()->create([
            'name' => 'Low Stock',
            'stock_quantity' => 2,
            'reorder_point' => 10,
            'is_active' => true,
        ]);

        $suggestions = $this->service->getReorderSuggestions();

        $this->assertCount(1, $suggestions);
        $this->assertEquals('Low Stock', $suggestions->first()['product_name']);
    }

    public function test_suggestions_excludes_sufficient_stock_products(): void
    {
        Product::factory()->create([
            'stock_quantity' => 100,
            'reorder_point' => 10,
            'is_active' => true,
        ]);

        $suggestions = $this->service->getReorderSuggestions();

        $this->assertCount(0, $suggestions);
    }

    // ---- checkAndTriggerReorders ----

    public function test_check_triggers_for_auto_reorder_enabled_products(): void
    {
        $owner = \App\Models\User::factory()->create(['role' => 'owner', 'is_active' => true]);
        Product::factory()->create([
            'stock_quantity' => 2,
            'reorder_point' => 10,
            'auto_reorder_enabled' => true,
            'is_active' => true,
        ]);

        $result = $this->service->checkAndTriggerReorders();

        $this->assertArrayHasKey('notifications_sent', $result);
        $this->assertArrayHasKey('products_checked', $result);
        $this->assertEquals(1, $result['products_checked']);
    }

    public function test_check_skips_auto_reorder_disabled_products(): void
    {
        Product::factory()->create([
            'stock_quantity' => 2,
            'reorder_point' => 10,
            'auto_reorder_enabled' => false,
            'is_active' => true,
        ]);

        $result = $this->service->checkAndTriggerReorders();

        $this->assertEquals(0, $result['products_checked']);
    }

    // ---- triggerReorder ----

    public function test_trigger_creates_auto_reorder_log(): void
    {
        $owner = \App\Models\User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $product = Product::factory()->create([
            'stock_quantity' => 2,
            'reorder_point' => 10,
            'is_active' => true,
        ]);

        $result = $this->service->triggerReorder($product, $owner->id);

        $this->assertEquals('notification_sent', $result);
        $this->assertDatabaseHas('auto_reorder_logs', [
            'product_id' => $product->id,
            'action_taken' => 'notification_sent',
        ]);
    }

    // ---- getStatistics ----

    public function test_statistics_returns_empty_for_no_logs(): void
    {
        $stats = $this->service->getStatistics();

        $this->assertEquals(0, $stats['total_triggers']);
        $this->assertEquals(0, $stats['pos_created']);
        $this->assertEquals(0, $stats['notifications_sent']);
    }

    public function test_statistics_counts_existing_logs(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        AutoReorderLog::create([
            'product_id' => $product->id,
            'current_stock' => 2,
            'reorder_point' => 10,
            'action_taken' => 'notification_sent',
            'suggested_quantity' => 50,
            'triggered_at' => now(),
        ]);

        $stats = $this->service->getStatistics();

        $this->assertEquals(1, $stats['total_triggers']);
        $this->assertEquals(1, $stats['notifications_sent']);
    }
}