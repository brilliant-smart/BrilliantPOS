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
        // Products table indexes
        Schema::table('products', function (Blueprint $table) {
            if (!$this->indexExists('products', 'products_name_index')) {
                $table->index('name');
            }
            if (!$this->indexExists('products', 'products_is_active_index')) {
                $table->index('is_active');
            }
            if (!$this->indexExists('products', 'products_is_featured_index')) {
                $table->index('is_featured');
            }
            if (!$this->indexExists('products', 'products_stock_quantity_index')) {
                $table->index('stock_quantity');
            }
            if (!$this->indexExists('products', 'products_department_id_is_active_index')) {
                $table->index(['department_id', 'is_active']);
            }
            if (!$this->indexExists('products', 'products_is_active_stock_quantity_index')) {
                $table->index(['is_active', 'stock_quantity']);
            }
        });

        // Sales table indexes
        Schema::table('sales', function (Blueprint $table) {
            if (!$this->indexExists('sales', 'sales_sale_date_index')) {
                $table->index('sale_date');
            }
            if (!$this->indexExists('sales', 'sales_total_amount_index')) {
                $table->index('total_amount');
            }
            if (!$this->indexExists('sales', 'sales_department_id_sale_date_index')) {
                $table->index(['department_id', 'sale_date']);
            }
        });

        // Users table indexes
        Schema::table('users', function (Blueprint $table) {
            if (!$this->indexExists('users', 'users_role_index')) {
                $table->index('role');
            }
            if (!$this->indexExists('users', 'users_department_id_index')) {
                $table->index('department_id');
            }
            if (!$this->indexExists('users', 'users_role_is_active_index')) {
                $table->index(['role', 'is_active']);
            }
        });

        // Purchase orders table indexes
        Schema::table('purchase_orders', function (Blueprint $table) {
            if (!$this->indexExists('purchase_orders', 'purchase_orders_order_date_index')) {
                $table->index('order_date');
            }
            if (!$this->indexExists('purchase_orders', 'purchase_orders_expected_delivery_date_index')) {
                $table->index('expected_delivery_date');
            }
            if (!$this->indexExists('purchase_orders', 'purchase_orders_supplier_id_status_index')) {
                $table->index(['supplier_id', 'status']);
            }
            if (!$this->indexExists('purchase_orders', 'purchase_orders_department_id_status_index')) {
                $table->index(['department_id', 'status']);
            }
        });

        // Stock movements table indexes
        Schema::table('stock_movements', function (Blueprint $table) {
            if (!$this->indexExists('stock_movements', 'stock_movements_created_at_index')) {
                $table->index('created_at');
            }
            if (!$this->indexExists('stock_movements', 'stock_movements_product_id_type_index')) {
                $table->index(['product_id', 'type']);
            }
        });

        // Orders table indexes
        Schema::table('orders', function (Blueprint $table) {
            if (!$this->indexExists('orders', 'orders_created_at_index')) {
                $table->index('created_at');
            }
            if (!$this->indexExists('orders', 'orders_customer_id_status_index')) {
                $table->index(['customer_id', 'status']);
            }
            if (!$this->indexExists('orders', 'orders_status_created_at_index')) {
                $table->index(['status', 'created_at']);
            }
        });

        // Suppliers table indexes
        Schema::table('suppliers', function (Blueprint $table) {
            if (!$this->indexExists('suppliers', 'suppliers_name_index')) {
                $table->index('name');
            }
            if (!$this->indexExists('suppliers', 'suppliers_is_active_index')) {
                $table->index('is_active');
            }
        });
    }

    private function indexExists($table, $index)
    {
        $conn = Schema::getConnection();
        $db = $conn->getDatabaseName();
        
        $exists = $conn->select(
            "SELECT COUNT(*) as count FROM information_schema.statistics 
             WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$db, $table, $index]
        );
        
        return $exists[0]->count > 0;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['is_featured']);
            $table->dropIndex(['stock_quantity']);
            $table->dropIndex(['department_id', 'is_active']);
            $table->dropIndex(['is_active', 'stock_quantity']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['sale_date']);
            $table->dropIndex(['total_amount']);
            $table->dropIndex(['department_id', 'sale_date']);
            $table->dropIndex(['created_by', 'sale_date']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex(['order_date']);
            $table->dropIndex(['expected_delivery_date']);
            $table->dropIndex(['supplier_id', 'status']);
            $table->dropIndex(['department_id', 'status']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['product_id', 'type']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['customer_id', 'status']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['is_active']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['department_id']);
            $table->dropIndex(['role', 'is_active']);
        });
    }
};
