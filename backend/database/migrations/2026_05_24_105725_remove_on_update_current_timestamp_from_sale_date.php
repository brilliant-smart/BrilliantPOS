<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove ON UPDATE CURRENT_TIMESTAMP from sale_date column.
     *
     * MySQL automatically adds ON UPDATE CURRENT_TIMESTAMP to the first TIMESTAMP
     * column in a table. This caused sale_date to be overwritten to "now" every
     * time a payment was recorded, making credit sales appear above newer sales
     * in the list sorted by sale_date.
     */
    public function up(): void
    {
        // SQLite doesn't have ON UPDATE CURRENT_TIMESTAMP, so only fix MySQL
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sales MODIFY sale_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");

            // Restore correct sale_date values for credit sales whose sale_date was
            // corrupted by the ON UPDATE CURRENT_TIMESTAMP behavior.
            DB::statement("UPDATE sales SET sale_date = created_at WHERE sale_date > created_at AND sale_date > DATE_ADD(updated_at, INTERVAL -2 SECOND)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE sales MODIFY sale_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
    }
};