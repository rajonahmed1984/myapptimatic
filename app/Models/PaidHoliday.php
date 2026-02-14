<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaidHoliday extends Model
{
    use HasFactory;

    protected $fillable = [
        'holiday_date',
        'name',
        'note',
        'is_paid',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'is_paid' => 'boolean',
    ];
}
