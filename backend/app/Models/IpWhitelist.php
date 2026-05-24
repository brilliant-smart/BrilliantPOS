<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpWhitelist extends Model
{
    protected $table = 'ip_whitelist';

    protected $fillable = [
        'ip_address',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}