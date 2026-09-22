<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's request to end a service, and the admin decision on it.
 *
 * Accepting does not wipe anything the customer already owes: the billing
 * cycle simply stops issuing new invoices, so open invoices stay collectable.
 */
class CancellationRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const TYPE_END_OF_PERIOD = 'end_of_period';

    public const TYPE_IMMEDIATE = 'immediate';

    protected $fillable = [
        'subscription_id',
        'customer_id',
        'type',
        'reason',
        'status',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'admin_note',
        'due_at_request',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'due_at_request' => 'decimal:2',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isImmediate(): bool
    {
        return $this->type === self::TYPE_IMMEDIATE;
    }

    public function typeLabel(): string
    {
        return $this->isImmediate() ? 'Immediate' : 'End of billing period';
    }
}
