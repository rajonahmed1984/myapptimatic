<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_item_id',
        'event',
        'old_status',
        'new_status',
        'user_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function payrollItem(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
