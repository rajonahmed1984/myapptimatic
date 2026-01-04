<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'is_paid',
        'default_allocation',
    ];

    protected $casts = [
        'is_paid' => 'boolean',
    ];
}
