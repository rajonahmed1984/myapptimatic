<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\HasActivityTracking;
use App\Models\EmployeeSession;
use App\Models\EmployeeActivityDaily;

class Employee extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use SoftDeletes;
    use HasActivityTracking;

    protected $fillable = [
        'user_id',
        'manager_id',
        'name',
        'email',
        'phone',
        'address',
        'designation',
        'department',
        'employment_type',
        'work_mode',
        'join_date',
        'exit_date',
        'status',
        'nid_path',
        'photo_path',
        'cv_path',
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

    // Activity tracking for employees uses dedicated tables.
    public function sessions(): HasMany
    {
        return $this->hasMany(EmployeeSession::class);
    }

    public function activityDaily(): HasMany
    {
        return $this->hasMany(EmployeeActivityDaily::class);
    }

    public function activitySessions(): HasMany
    {
        return $this->hasMany(EmployeeSession::class);
    }

    public function activityDailyRecords(): HasMany
    {
        return $this->hasMany(EmployeeActivityDaily::class);
    }

    public function activeCompensation(): HasOne
    {
        return $this->hasOne(EmployeeCompensation::class)
            ->where('is_active', true)
            ->latestOfMany('effective_from');
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'employee_project')->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isOnline(int $minutes = 2): bool
    {
        return $this->sessions()
            ->whereNull('logout_at')
            ->where('last_seen_at', '>=', now()->subMinutes($minutes))
            ->exists();
    }
}
