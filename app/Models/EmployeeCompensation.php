<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeCompensation extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'salary_type',
        'currency',
        'basic_pay',
        'allowances',
        'deductions',
        'overtime_rate',
        'overtime_enabled',
        'effective_from',
        'effective_to',
        'is_active',
        'set_by',
    ];

    protected $casts = [
        'allowances' => 'array',
        'deductions' => 'array',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
        'overtime_enabled' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
