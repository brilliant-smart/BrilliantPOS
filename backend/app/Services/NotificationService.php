<?php

namespace App\Services;

use App\Models\NotificationSetting;
use App\Models\NotificationLog;
use App\Models\User;
use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Send low stock alert
     */
    public function sendLowStockAlert(Product $product)
    {
        $setting = NotificationSetting::where('key', 'low_stock_alert')->first();
        
        if (!$setting) {
            return;
        }

        $message = "Low stock alert: {$product->name} (SKU: {$product->sku}) has only {$product->stock_quantity} units remaining. Reorder point: {$product->reorder_point}";

        // Get recipients (owners and managers)
        $recipients = User::whereIn('role', ['owner', 'manager'])
            ->where('is_active', true)
            ->get();

        foreach ($recipients as $recipient) {
            if ($setting->email_enabled && $recipient->email) {
                $this->sendEmail(
                    $recipient->email,
                    'Low Stock Alert',
                    $message,
                    'low_stock',
                    $recipient->id,
                    ['product_id' => $product->id]
                );
            }

            if ($setting->sms_enabled && $recipient->phone) {
                $this->sendSMS(
                    $recipient->phone,
                    $message,
                    'low_stock',
                    $recipient->id,
                    ['product_id' => $product->id]
                );
            }
        }
    }

    /**
     * Send expiry warning
     */
    public function sendExpiryWarning(Product $product, $daysUntilExpiry)
    {
        $setting = NotificationSetting::where('key', 'expiry_warning')->first();
        
        if (!$setting) {
            return;
        }

        $message = "Expiry warning: {$product->name} (SKU: {$product->sku}) will expire in {$daysUntilExpiry} days on {$product->expiry_date->format('Y-m-d')}";

        $recipients = User::whereIn('role', ['owner', 'manager'])
            ->where('is_active', true)
            ->get();

        foreach ($recipients as $recipient) {
            if ($setting->email_enabled && $recipient->email) {
                $this->sendEmail(
                    $recipient->email,
                    'Product Expiry Warning',
                    $message,
                    'expiry_warning',
                    $recipient->id,
                    ['product_id' => $product->id, 'days_until_expiry' => $daysUntilExpiry]
                );
            }

            if ($setting->sms_enabled && $recipient->phone) {
                $this->sendSMS(
                    $recipient->phone,
                    $message,
                    'expiry_warning',
                    $recipient->id,
                    ['product_id' => $product->id]
                );
            }
        }
    }

    /**
     * Send PO approval notification
     */
    public function sendPOApprovalNotification(PurchaseOrder $po)
    {
        $setting = NotificationSetting::where('key', 'po_approval')->first();
        
        if (!$setting) {
            return;
        }

        $message = "Purchase Order {$po->po_number} requires approval. Total amount: ₦" . number_format($po->total_amount, 2);

        $recipients = User::whereIn('role', ['owner', 'manager'])
            ->where('is_active', true)
            ->get();

        foreach ($recipients as $recipient) {
            if ($setting->email_enabled && $recipient->email) {
                $this->sendEmail(
                    $recipient->email,
                    'Purchase Order Approval Required',
                    $message,
                    'po_approval',
                    $recipient->id,
                    ['po_id' => $po->id]
                );
            }

            if ($setting->sms_enabled && $recipient->phone) {
                $this->sendSMS(
                    $recipient->phone,
                    $message,
                    'po_approval',
                    $recipient->id,
                    ['po_id' => $po->id]
                );
            }
        }
    }

    /**
     * Send PO received notification
     */
    public function sendPOReceivedNotification(PurchaseOrder $po)
    {
        $setting = NotificationSetting::where('key', 'po_received')->first();
        
        if (!$setting) {
            return;
        }

        $message = "Purchase Order {$po->po_number} has been received. Total amount: ₦" . number_format($po->total_amount, 2);

        // Notify the creator
        $creator = $po->creator;
        
        if ($creator && $setting->email_enabled && $creator->email) {
            $this->sendEmail(
                $creator->email,
                'Purchase Order Received',
                $message,
                'po_received',
                $creator->id,
                ['po_id' => $po->id]
            );
        }
    }

    /**
     * Send payment reminder
     */
    public function sendPaymentReminder($recipient, $amount, $dueDate, $reference)
    {
        $setting = NotificationSetting::where('key', 'payment_reminder')->first();
        
        if (!$setting) {
            return;
        }

        $message = "Payment reminder: ₦" . number_format($amount, 2) . " is due on {$dueDate}. Reference: {$reference}";

        if ($setting->email_enabled && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $this->sendEmail(
                $recipient,
                'Payment Reminder',
                $message,
                'payment_reminder',
                null,
                ['amount' => $amount, 'due_date' => $dueDate, 'reference' => $reference]
            );
        }

        if ($setting->sms_enabled && preg_match('/^[\d\s\+\-\(\)]+$/', $recipient)) {
            $this->sendSMS(
                $recipient,
                $message,
                'payment_reminder',
                null,
                ['amount' => $amount, 'due_date' => $dueDate]
            );
        }
    }

    /**
     * Send email
     */
    private function sendEmail($to, $subject, $message, $category, $userId = null, $metadata = [])
    {
        $log = NotificationLog::create([
            'type' => 'email',
            'category' => $category,
            'recipient' => $to,
            'user_id' => $userId,
            'subject' => $subject,
            'message' => $message,
            'status' => 'pending',
            'metadata' => $metadata,
        ]);

        try {
            // Using Laravel's basic mail function
            Mail::raw($message, function ($mail) use ($to, $subject) {
                $mail->to($to)
                     ->subject($subject);
            });

            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('Email notification failed', [
                'recipient' => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send SMS
     */
    private function sendSMS($to, $message, $category, $userId = null, $metadata = [])
    {
        $log = NotificationLog::create([
            'type' => 'sms',
            'category' => $category,
            'recipient' => $to,
            'user_id' => $userId,
            'subject' => null,
            'message' => $message,
            'status' => 'pending',
            'metadata' => $metadata,
        ]);

        try {
            // SMS integration placeholder - integrate with Twilio, Nexmo, or local SMS gateway
            // For now, we just log it
            Log::info('SMS notification', [
                'to' => $to,
                'message' => $message,
            ]);

            // TODO: Implement actual SMS sending
            // Example: $this->twilioClient->messages->create($to, ['from' => $fromNumber, 'body' => $message]);

            $log->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('SMS notification failed', [
                'recipient' => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get notification settings
     */
    public function getSettings()
    {
        return NotificationSetting::all();
    }

    /**
     * Update notification setting
     */
    public function updateSetting($key, array $data)
    {
        $setting = NotificationSetting::where('key', $key)->firstOrFail();
        $setting->update($data);
        return $setting;
    }

    /**
     * Get notification logs
     */
    public function getLogs($filters = [])
    {
        $query = NotificationLog::with('user');

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }
}
