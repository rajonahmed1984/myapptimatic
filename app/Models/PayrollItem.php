<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_period_id',
        'employee_id',
        'status',
        'pay_type',
        'currency',
        'base_pay',
        'timesheet_hours',
        'overtime_hours',
        'overtime_rate',
        'overtime_enabled',
        'bonuses',
        'penalties',
        'advances',
        'deductions',
        'gross_pay',
        'net_pay',
        'payment_reference',
        'paid_at',
        'locked_at',
    ];

    protected $casts = [
        'bonuses' => 'array',
        'penalties' => 'array',
        'advances' => 'array',
        'deductions' => 'array',
        'overtime_enabled' => 'boolean',
        'paid_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(PayrollAuditLog::class);
    }
}
