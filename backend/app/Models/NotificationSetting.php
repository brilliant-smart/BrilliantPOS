<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'description',
        'email_enabled',
        'sms_enabled',
        'in_app_enabled',
        'recipients',
        'threshold_value',
    ];

    protected $casts = [
        'email_enabled' => 'boolean',
        'sms_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
        'recipients' => 'array',
        'threshold_value' => 'integer',
    ];

    /**
     * Check if notification type is enabled
     */
    public function isEnabled(string $type): bool
    {
        return match($type) {
            'email' => $this->email_enabled,
            'sms' => $this->sms_enabled,
            'in_app' => $this->in_app_enabled,
            default => false,
        };
    }
}
