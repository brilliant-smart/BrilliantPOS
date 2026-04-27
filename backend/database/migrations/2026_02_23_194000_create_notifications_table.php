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
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('in_app_enabled')->default(true);
            $table->json('recipients')->nullable(); // Array of user IDs or roles
            $table->integer('threshold_value')->nullable(); // For stock alerts
            $table->timestamps();
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // email, sms, in_app
            $table->string('category'); // low_stock, expiry_warning, po_approval, etc.
            $table->string('recipient'); // email or phone number
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('subject')->nullable();
            $table->text('message');
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['type', 'status']);
            $table->index('category');
        });

        // Insert default notification settings
        DB::table('notification_settings')->insert([
            [
                'key' => 'low_stock_alert',
                'name' => 'Low Stock Alert',
                'description' => 'Notify when product stock falls below reorder point',
                'email_enabled' => true,
                'sms_enabled' => false,
                'in_app_enabled' => true,
                'threshold_value' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'expiry_warning',
                'name' => 'Product Expiry Warning',
                'description' => 'Notify when products are expiring soon',
                'email_enabled' => true,
                'sms_enabled' => false,
                'in_app_enabled' => true,
                'threshold_value' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'po_approval',
                'name' => 'Purchase Order Approval',
                'description' => 'Notify when purchase order needs approval',
                'email_enabled' => true,
                'sms_enabled' => false,
                'in_app_enabled' => true,
                'threshold_value' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'po_received',
                'name' => 'Purchase Order Received',
                'description' => 'Notify when purchase order is received',
                'email_enabled' => true,
                'sms_enabled' => false,
                'in_app_enabled' => true,
                'threshold_value' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'sale_created',
                'name' => 'Sale Created',
                'description' => 'Notify when a new sale is created',
                'email_enabled' => false,
                'sms_enabled' => false,
                'in_app_enabled' => true,
                'threshold_value' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'payment_reminder',
                'name' => 'Payment Reminder',
                'description' => 'Remind about pending payments',
                'email_enabled' => true,
                'sms_enabled' => true,
                'in_app_enabled' => true,
                'threshold_value' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_settings');
    }
};
