<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeWorkSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'work_date',
        'active_seconds',
        'required_seconds',
        'generated_salary_amount',
        'status',
    ];

    protected $casts = [
        'work_date' => 'date',
        'active_seconds' => 'integer',
        'required_seconds' => 'integer',
        'generated_salary_amount' => 'float',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
