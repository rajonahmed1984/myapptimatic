<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionPayout extends Model
{
    protected $fillable = [
        'sales_representative_id',
        'project_id',
        'type',
        'total_amount',
        'currency',
        'payout_method',
        'reference',
        'note',
        'status',
        'paid_at',
        'reversed_at',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(SalesRepresentative::class, 'sales_representative_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(CommissionEarning::class, 'commission_payout_id');
    }
}
