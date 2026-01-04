<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_key',
        'start_date',
        'end_date',
        'status',
        'finalized_at',
        'paid_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'finalized_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }
}
