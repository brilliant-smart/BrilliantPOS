<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insert([
            'key' => 'receipt_prompt_enabled',
            'value' => '1',
            'type' => 'boolean',
            'group' => 'general',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'receipt_prompt_enabled')->delete();
    }
};