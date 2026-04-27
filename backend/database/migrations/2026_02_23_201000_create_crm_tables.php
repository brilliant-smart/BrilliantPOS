<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // CRM settings
        Schema::create('crm_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->boolean('loyalty_program_enabled')->default(false);
            $table->integer('points_per_currency')->default(1); // 1 point per ₦1
            $table->decimal('currency_per_point', 10, 2)->default(0.10); // ₦0.10 per point
            $table->timestamps();
        });

        DB::table('crm_settings')->insert([
            'enabled' => false,
            'loyalty_program_enabled' => false,
            'points_per_currency' => 1,
            'currency_per_point' => 0.10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Customer loyalty points
        Schema::create('customer_loyalty_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->integer('points')->default(0);
            $table->integer('lifetime_points')->default(0);
            $table->string('tier')->default('bronze'); // bronze, silver, gold, platinum
            $table->timestamps();
            
            $table->unique('customer_id');
        });

        // Loyalty transactions
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['earned', 'redeemed', 'expired', 'adjusted'])->index();
            $table->integer('points');
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            $table->index(['customer_id', 'created_at']);
        });

        // Discount coupons
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['percentage', 'fixed_amount', 'free_shipping']);
            $table->decimal('value', 15, 2); // Percentage or amount
            $table->decimal('min_order_amount', 15, 2)->default(0);
            $table->decimal('max_discount_amount', 15, 2)->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_limit_per_customer')->default(1);
            $table->integer('used_count')->default(0);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('applicable_products')->nullable(); // Array of product IDs
            $table->json('applicable_categories')->nullable(); // Array of department IDs
            $table->timestamps();
            
            $table->index('code');
            $table->index('is_active');
        });

        // Coupon usage tracking
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->onDelete('cascade');
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->decimal('discount_amount', 15, 2);
            $table->timestamps();
            
            $table->index(['coupon_id', 'customer_id']);
        });

        // Customer segments
        Schema::create('customer_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('criteria'); // Conditions for auto-segmentation
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Customer segment membership
        Schema::create('customer_segment_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('segment_id')->constrained('customer_segments')->onDelete('cascade');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();
            
            $table->unique(['customer_id', 'segment_id']);
        });

        // Email campaigns
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('subject');
            $table->text('content');
            $table->enum('status', ['draft', 'scheduled', 'sending', 'sent', 'cancelled'])->default('draft');
            $table->foreignId('segment_id')->nullable()->constrained('customer_segments')->onDelete('set null');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->integer('recipients_count')->default(0);
            $table->integer('sent_count')->default(0);
            $table->integer('opened_count')->default(0);
            $table->integer('clicked_count')->default(0);
            $table->timestamps();
        });

        // Customer notes/interactions
        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('type', ['note', 'call', 'email', 'meeting', 'complaint', 'feedback']);
            $table->string('subject')->nullable();
            $table->text('content');
            $table->timestamp('interaction_date')->useCurrent();
            $table->timestamps();
            
            $table->index(['customer_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_notes');
        Schema::dropIfExists('email_campaigns');
        Schema::dropIfExists('customer_segment_members');
        Schema::dropIfExists('customer_segments');
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('customer_loyalty_points');
        Schema::dropIfExists('crm_settings');
    }
};
