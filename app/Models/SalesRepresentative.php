<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\CommissionEarning;
use App\Models\CommissionPayout;
use App\Models\Concerns\HasActivityTracking;

class SalesRepresentative extends Model
{
    use HasActivityTracking;

    protected $fillable = [
        'user_id',
        'employee_id',
        'name',
        'email',
        'phone',
        'status',
        'payout_method_default',
        'payout_details_encrypted',
        'metadata',
        'avatar_path',
        'nid_path',
        'cv_path',
    ];

    protected $casts = [
        'payout_details_encrypted' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(CommissionEarning::class, 'sales_representative_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(CommissionPayout::class, 'sales_representative_id');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_sales_representative')
            ->withPivot('amount')
            ->withTimestamps();
    }
}
