<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') { return; }

        // Step 1: Widen the enum to include both old and new values
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('master_admin', 'section_head', 'owner', 'manager', 'cashier') NOT NULL");

        // Step 2: Migrate data
        DB::table('users')->where('role', 'master_admin')->update(['role' => 'owner']);
        DB::table('users')->where('role', 'section_head')->update(['role' => 'manager']);

        // Step 3: Narrow the enum to only new values
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('owner', 'manager', 'cashier') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') { return; }

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('owner', 'manager', 'cashier', 'master_admin', 'section_head') NOT NULL");

        DB::table('users')->where('role', 'owner')->update(['role' => 'master_admin']);
        DB::table('users')->where('role', 'manager')->update(['role' => 'section_head']);
        DB::table('users')->where('role', 'cashier')->update(['role' => 'section_head']);

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('master_admin', 'section_head') NOT NULL");
    }
};