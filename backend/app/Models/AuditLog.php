<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuditLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'description',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    protected $appends = ['action_label', 'model_label', 'action_category'];

    /**
     * Get the user
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the auditable model
     */
    public function auditable()
    {
        return $this->morphTo('model', 'model_type', 'model_id');
    }

    /**
     * Human-readable action labels (enterprise standard: past-tense verb-noun)
     */
    private static array $actionLabels = [
        // Authentication
        'auth.login' => 'Signed In',
        'auth.logout' => 'Signed Out',
        // Users
        'user.create' => 'Created User',
        'user.update' => 'Updated User',
        'user.deactivate' => 'Deactivated User',
        'user.delete' => 'Deleted User',
        'user.password_change' => 'Changed Password',
        // Products
        'product.create' => 'Created Product',
        'product.update' => 'Updated Product',
        'product.delete' => 'Deleted Product',
        // Sales
        'sale.create' => 'Created Sale',
        'sale.payment' => 'Recorded Payment',
        'sale.void' => 'Voided Sale',
        'sale.delete' => 'Deleted Sale',
        // Purchase Orders
        'purchase_order.create' => 'Created Purchase Order',
        'purchase_order.update' => 'Updated Purchase Order',
        'purchase_order.approve' => 'Approved Purchase Order',
        'purchase_order.reject' => 'Rejected Purchase Order',
        'purchase_order.cancel' => 'Cancelled Purchase Order',
        'purchase_order.receive' => 'Received Goods',
        'purchase_order.payment' => 'Recorded PO Payment',
        'purchase_order.delete' => 'Deleted Purchase Order',
        // Suppliers
        'supplier.create' => 'Created Supplier',
        'supplier.update' => 'Updated Supplier',
        'supplier.delete' => 'Deleted Supplier',
        'supplier.toggle_status' => 'Changed Supplier Status',
        // Expenses
        'expense.create' => 'Recorded Expense',
        'expense.update' => 'Updated Expense',
        'expense.delete' => 'Deleted Expense',
        // Expense Categories
        'expense_category.create' => 'Created Expense Category',
        'expense_category.update' => 'Updated Expense Category',
        'expense_category.delete' => 'Deleted Expense Category',
        // Inventory / Stock
        'stock.add' => 'Added Stock',
        'stock.reduce' => 'Reduced Stock',
        'stock.adjust' => 'Adjusted Stock',
        'stock.bulk_adjust' => 'Bulk Stock Adjustment',
        // Batches
        'batch.create' => 'Created Batch',
        'batch.update' => 'Updated Batch',
        'batch.mark_expired' => 'Marked Batch Expired',
        // Settings
        'settings.update' => 'Updated Settings',
        // Security
        'security_settings.update' => 'Updated Security Settings',
        'ip_whitelist.create' => 'Added IP to Whitelist',
        'ip_whitelist.delete' => 'Removed IP from Whitelist',
        '2fa.enable' => 'Enabled 2FA',
        '2fa.disable' => 'Disabled 2FA',
        // Backups
        'backup.create' => 'Created Backup',
        'backup.delete' => 'Deleted Backup',
        'backup.upload' => 'Uploaded Backup',
        'backup.restore' => 'Restored Backup',
    ];

    /**
     * Human-readable model type labels
     */
    private static array $modelLabels = [
        'App\Models\User' => 'User',
        'App\Models\Product' => 'Product',
        'App\Models\Sale' => 'Sale',
        'App\Models\PurchaseOrder' => 'Purchase Order',
        'App\Models\Supplier' => 'Supplier',
        'App\Models\Expense' => 'Expense',
        'App\Models\ExpenseCategory' => 'Expense Category',
        'App\Models\BatchTracking' => 'Batch',
        'App\Models\Setting' => 'Settings',
        'SecuritySetting' => 'Security Settings',
        'IpWhitelist' => 'IP Whitelist',
        'Backup' => 'Backup',
    ];

    /**
     * Action category mapping for color coding
     */
    private static array $actionCategories = [
        'auth.login' => 'auth',
        'auth.logout' => 'auth',
        'user.create' => 'create',
        'user.update' => 'update',
        'user.deactivate' => 'delete',
        'user.delete' => 'delete',
        'user.password_change' => 'security',
        'product.create' => 'create',
        'product.update' => 'update',
        'product.delete' => 'delete',
        'sale.create' => 'create',
        'sale.payment' => 'update',
        'sale.void' => 'void',
        'sale.delete' => 'delete',
        'purchase_order.create' => 'create',
        'purchase_order.update' => 'update',
        'purchase_order.approve' => 'approve',
        'purchase_order.reject' => 'void',
        'purchase_order.cancel' => 'void',
        'purchase_order.receive' => 'update',
        'purchase_order.payment' => 'update',
        'purchase_order.delete' => 'delete',
        'supplier.create' => 'create',
        'supplier.update' => 'update',
        'supplier.delete' => 'delete',
        'supplier.toggle_status' => 'update',
        'expense.create' => 'create',
        'expense.update' => 'update',
        'expense.delete' => 'delete',
        'expense_category.create' => 'create',
        'expense_category.update' => 'update',
        'expense_category.delete' => 'delete',
        'stock.add' => 'stock',
        'stock.reduce' => 'stock',
        'stock.adjust' => 'stock',
        'stock.bulk_adjust' => 'stock',
        'batch.create' => 'create',
        'batch.update' => 'update',
        'batch.mark_expired' => 'void',
        'settings.update' => 'update',
        'security_settings.update' => 'security',
        'ip_whitelist.create' => 'security',
        'ip_whitelist.delete' => 'security',
        '2fa.enable' => 'security',
        '2fa.disable' => 'security',
        'backup.create' => 'backup',
        'backup.delete' => 'delete',
        'backup.upload' => 'backup',
        'backup.restore' => 'backup',
    ];

    public function getActionLabelAttribute(): string
    {
        if ($this->action === null) {
            return 'Unknown Action';
        }
        return self::$actionLabels[$this->action] ?? $this->formatFallbackAction($this->action);
    }

    public function getModelLabelAttribute(): string
    {
        if ($this->model_type === null) {
            return 'System';
        }
        return self::$modelLabels[$this->model_type] ?? $this->formatFallbackModel($this->model_type);
    }

    public function getActionCategoryAttribute(): string
    {
        if ($this->action === null) {
            return 'other';
        }
        return self::$actionCategories[$this->action] ?? 'other';
    }

    private function formatFallbackAction(string $action): string
    {
        $parts = explode('.', $action, 2);
        if (count($parts) === 2) {
            return ucfirst($parts[1]) . ' ' . ucfirst(str_replace('_', ' ', $parts[0]));
        }
        return ucfirst(str_replace(['_', '.'], ' ', $action));
    }

    private function formatFallbackModel(string $modelType): string
    {
        if (str_starts_with($modelType, 'App\\Models\\')) {
            $class = class_basename($modelType);
            return preg_replace('/(?=[A-Z])/', ' ', $class);
        }
        return preg_replace('/(?=[A-Z])/', ' ', $modelType);
    }

    /**
     * Create audit log entry with a Model instance
     */
    public static function log(string $action, Model $model, ?array $oldValues = null, ?array $newValues = null, ?string $description = null)
    {
        return self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'description' => $description,
        ]);
    }

    /**
     * Create audit log entry without a Model instance (for raw DB operations)
     */
    public static function logAction(string $action, string $modelType, ?int $modelId = null, ?array $oldValues = null, ?array $newValues = null, ?string $description = null)
    {
        return self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'description' => $description,
        ]);
    }
}
