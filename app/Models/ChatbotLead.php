<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotLead extends Model
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'product_interest',
        'transcript',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];
}
