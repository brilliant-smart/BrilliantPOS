<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Webhooks
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->json('events'); // Array of events to listen to
            $table->string('secret');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Webhook delivery logs
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained()->onDelete('cascade');
            $table->string('event');
            $table->json('payload');
            $table->integer('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->boolean('success')->default(false);
            $table->timestamp('delivered_at')->useCurrent();
            $table->timestamps();
            
            $table->index(['webhook_id', 'delivered_at']);
        });

        // Integration settings
        Schema::create('integration_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // mailchimp, quickbooks, etc.
            $table->boolean('enabled')->default(false);
            $table->json('credentials')->nullable();
            $table->json('configuration')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
            
            $table->unique('provider');
        });

        // API Keys for external access
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->json('permissions')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_keys');
        Schema::dropIfExists('integration_settings');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
    }
};
