<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->string('group')->default('general');
            $table->string('label')->nullable();
            $table->timestamps();
        });

        DB::table('settings')->insert([
            ['key' => 'store_name', 'value' => 'My Store', 'type' => 'string', 'group' => 'general', 'label' => 'Store Name', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'store_phone', 'value' => '', 'type' => 'string', 'group' => 'general', 'label' => 'Store Phone', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'store_email', 'value' => '', 'type' => 'string', 'group' => 'general', 'label' => 'Store Email', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'store_address', 'value' => '', 'type' => 'string', 'group' => 'general', 'label' => 'Store Address', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'store_tagline', 'value' => '', 'type' => 'string', 'group' => 'general', 'label' => 'Store Tagline', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'receipt_footer', 'value' => 'Thank you for your purchase!', 'type' => 'string', 'group' => 'receipt', 'label' => 'Receipt Footer Message', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'currency_symbol', 'value' => '₦', 'type' => 'string', 'group' => 'general', 'label' => 'Currency Symbol', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'vat_rate', 'value' => '0', 'type' => 'float', 'group' => 'general', 'label' => 'VAT Rate (%)', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};