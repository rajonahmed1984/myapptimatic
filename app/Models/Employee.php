<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'manager_id',
        'name',
        'email',
        'phone',
        'designation',
        'department',
        'employment_type',
        'work_mode',
        'join_date',
        'exit_date',
        'status',
    ];

    protected $casts = [
        'join_date' => 'date',
        'exit_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function compensations(): HasMany
    {
        return $this->hasMany(EmployeeCompensation::class);
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function activeCompensation(): HasOne
    {
        return $this->hasOne(EmployeeCompensation::class)
            ->where('is_active', true)
            ->latestOfMany('effective_from');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
