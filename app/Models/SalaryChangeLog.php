<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryChangeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'changed_by',
        'old_compensation',
        'new_compensation',
        'reason',
        'changed_at',
    ];

    protected $casts = [
        'old_compensation' => 'array',
        'new_compensation' => 'array',
        'changed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
