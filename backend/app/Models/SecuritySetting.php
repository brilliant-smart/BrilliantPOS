<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecuritySetting extends Model
{
    protected $table = 'security_settings';

    public $timestamps = false;

    protected $fillable = [
        'ip_whitelist_enabled',
        'two_fa_required',
        'password_min_length',
        'password_require_uppercase',
        'password_require_numbers',
        'password_require_symbols',
        'password_expiry_days',
        'max_login_attempts',
        'lockout_duration_minutes',
        'session_timeout_minutes',
    ];

    protected $casts = [
        'ip_whitelist_enabled' => 'boolean',
        'two_fa_required' => 'boolean',
        'password_min_length' => 'integer',
        'password_require_uppercase' => 'boolean',
        'password_require_numbers' => 'boolean',
        'password_require_symbols' => 'boolean',
        'password_expiry_days' => 'integer',
        'max_login_attempts' => 'integer',
        'lockout_duration_minutes' => 'integer',
        'session_timeout_minutes' => 'integer',
    ];
}