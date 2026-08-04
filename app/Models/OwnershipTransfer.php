<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OwnershipTransfer extends Model
{
    // A real declared property (not an Eloquent attribute) so it never gets
    // treated as a dirty column and written to the DB on save()/update() —
    // it only ever exists on the in-memory instance returned by
    // ProjectTransferService::initiate(), to hand the plaintext token to the
    // caller for emailing. It is never persisted anywhere; only its hash is.
    public ?string $plainToken = null;

    protected $fillable = [
        'project_id',
        'subscription_id',
        'from_customer_id',
        'to_customer_id',
        'initiated_by',
        'initiated_by_ip',
        'status',
        'token_hash',
        'token_expires_at',
        'scheduled_for',
        'accepted_at',
        'accepted_by_user_id',
        'accepted_by_ip',
        'rejected_at',
        'rejected_by_user_id',
        'rejected_by_ip',
        'executed_at',
        'cancelled_at',
        'cancelled_by_user_id',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'scheduled_for' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'executed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function fromCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'from_customer_id');
    }

    public function toCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'to_customer_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(OwnershipTransferLog::class);
    }

    public function isAcceptable(): bool
    {
        return $this->status === 'pending'
            && $this->token_expires_at !== null
            && $this->token_expires_at->isFuture();
    }
}
