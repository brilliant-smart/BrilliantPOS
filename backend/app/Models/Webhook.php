<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    protected $fillable = [
        'name',
        'url',
        'events',
        'is_active',
        'secret',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
    ];
}