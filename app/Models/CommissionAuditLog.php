<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class CommissionAuditLog extends Model
{
    protected $fillable = [
        'sales_representative_id',
        'commission_earning_id',
        'commission_payout_id',
        'action',
        'status_from',
        'status_to',
        'description',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(SalesRepresentative::class, 'sales_representative_id');
    }

    public function earning(): BelongsTo
    {
        return $this->belongsTo(CommissionEarning::class, 'commission_earning_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(CommissionPayout::class, 'commission_payout_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
