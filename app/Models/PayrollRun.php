<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_period_id',
        'status',
        'triggered_by',
        'notes',
        'ran_at',
    ];

    protected $casts = [
        'ran_at' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
