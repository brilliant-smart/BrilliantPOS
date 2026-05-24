<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TwoFactorAuth extends Model
{
    protected $table = 'two_factor_auth';

    protected $fillable = [
        'user_id',
        'enabled',
        'secret',
        'recovery_codes',
        'enabled_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'recovery_codes' => 'array',
        'enabled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}