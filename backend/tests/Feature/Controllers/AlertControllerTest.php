<?php

namespace Tests\Feature\Controllers;

use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertControllerTest extends TestCase
{
    use RefreshDatabase;

    // ---- Low Stock Alerts ----

    public function test_low_stock_returns_products_below_reorder_point(): void
    {
        $lowProduct = $this->createProduct([
            'name' => 'Low Stock Item',
            'stock_quantity' => 3,
            'reorder_point' => 10,
            'is_active' => true,
        ]);
        $okProduct = $this->createProduct([
            'name' => 'OK Stock Item',
            'stock_quantity' => 50,
            'reorder_point' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAsOwner()->getJson('/api/alerts/low-stock');

        $response->assertStatus(200);
        $alerts = $response->json('alerts');
        $found = collect($alerts)->first(fn ($p) => $p['id'] === $lowProduct->id);
        $notFound = collect($alerts)->first(fn ($p) => $p['id'] === $okProduct->id);
        $this->assertNotNull($found, 'Low stock product should appear');
        $this->assertNull($notFound, 'Well-stocked product should not appear');
    }

    public function test_low_stock_zero_quantity_is_critical_severity(): void
    {
        $this->createProduct([
            'stock_quantity' => 0,
            'reorder_point' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAsOwner()->getJson('/api/alerts/low-stock');

        $response->assertStatus(200);
        $alerts = $response->json('alerts');
        $this->assertNotEmpty($alerts);
        $this->assertEquals('critical', $alerts[0]['severity']);
    }

    public function test_cashier_can_view_low_stock_alerts(): void
    {
        $response = $this->actingAsCashier()->getJson('/api/alerts/low-stock');

        $response->assertStatus(200);
    }

    // ---- Expiring Batches ----

    public function test_expiring_batches_returns_batches_within_threshold(): void
    {
        $product = $this->createProduct();
        ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 10,
            'expiry_date' => now()->addDays(30),
            'status' => 'active',
        ]);

        $response = $this->actingAsOwner()->getJson('/api/alerts/expiring-batches');

        $response->assertStatus(200);
        $alerts = $response->json('alerts');
        $this->assertNotEmpty($alerts);
    }

    public function test_expiring_batches_with_custom_days_parameter(): void
    {
        $product = $this->createProduct();
        // Batch expiring in 45 days — should appear with days=60 but not days=30
        ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 10,
            'expiry_date' => now()->addDays(45),
            'status' => 'active',
        ]);

        $response30 = $this->actingAsOwner()->getJson('/api/alerts/expiring-batches?days=30');
        $response60 = $this->actingAsOwner()->getJson('/api/alerts/expiring-batches?days=60');

        $response30->assertStatus(200);
        $response60->assertStatus(200);
        $alerts30 = $response30->json('alerts');
        $alerts60 = $response60->json('alerts');
        $found30 = collect($alerts30)->first(fn ($b) => $b['product_id'] === $product->id);
        $found60 = collect($alerts60)->first(fn ($b) => $b['product_id'] === $product->id);
        $this->assertNull($found30, 'Batch at 45 days should not appear in 30-day window');
        $this->assertNotNull($found60, 'Batch at 45 days should appear in 60-day window');
    }

    public function test_expiring_batches_excludes_zero_quantity(): void
    {
        $product = $this->createProduct();
        ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 0,
            'expiry_date' => now()->addDays(30),
            'status' => 'active',
        ]);

        $response = $this->actingAsOwner()->getJson('/api/alerts/expiring-batches');

        $response->assertStatus(200);
        $alerts = $response->json('alerts');
        $found = collect($alerts)->first(fn ($b) => $b['product_id'] === $product->id);
        $this->assertNull($found, 'Zero-quantity batch should not appear in expiring alerts');
    }

    // ---- Expired Batches ----

    public function test_expired_batches_returns_past_expiry(): void
    {
        $product = $this->createProduct();
        ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 5,
            'expiry_date' => now()->subDays(10),
            'status' => 'active',
        ]);

        $response = $this->actingAsOwner()->getJson('/api/alerts/expired-batches');

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('alerts'));
    }

    // ---- Mark Expired Batches ----

    public function test_owner_can_mark_expired_batches(): void
    {
        $product = $this->createProduct(['stock_quantity' => 100]);
        $batch = ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 20,
            'expiry_date' => now()->subDay(),
            'status' => 'active',
        ]);

        $response = $this->actingAsOwner()->postJson('/api/alerts/mark-expired');

        $response->assertStatus(200);
        $this->assertEquals('expired', $batch->fresh()->status);
        $this->assertEquals(0, $batch->fresh()->quantity_remaining);
        $this->assertEquals(80, $product->fresh()->stock_quantity);
    }

    public function test_mark_specific_batch_ids_only(): void
    {
        $product = $this->createProduct(['stock_quantity' => 200]);
        $batch1 = ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 10,
            'expiry_date' => now()->subDay(),
            'status' => 'active',
        ]);
        $batch2 = ProductBatch::factory()->create([
            'product_id' => $product->id,
            'quantity_remaining' => 30,
            'expiry_date' => now()->subDays(5),
            'status' => 'active',
        ]);

        $response = $this->actingAsOwner()->postJson('/api/alerts/mark-expired', [
            'batch_ids' => [$batch1->id],
        ]);

        $response->assertStatus(200);
        $this->assertEquals('expired', $batch1->fresh()->status);
        $this->assertEquals('active', $batch2->fresh()->status);
    }

    // ---- Alert Summary ----

    public function test_alert_summary_returns_counts(): void
    {
        $this->createProduct([
            'stock_quantity' => 2,
            'reorder_point' => 10,
            'is_active' => true,
        ]);

        $response = $this->actingAsOwner()->getJson('/api/alerts/summary');

        $response->assertStatus(200);
        $response->assertJsonStructure(['low_stock_count', 'expiring_count', 'expired_count', 'total_alerts']);
        $this->assertGreaterThanOrEqual(1, $response->json('low_stock_count'));
        $this->assertGreaterThanOrEqual(0, $response->json('total_alerts'));
    }
}