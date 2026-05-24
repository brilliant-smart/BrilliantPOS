<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insert([
            'key' => 'credit_overdue_threshold_days',
            'value' => '7',
            'type' => 'integer',
            'group' => 'general',
            'label' => 'Credit Overdue Threshold (Days)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'credit_overdue_threshold_days')->delete();
    }
};