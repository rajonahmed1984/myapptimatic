<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected $fillable = [
        'customer_id',
        'user_id',
        'subject',
        'status',
        'priority',
        'last_reply_at',
        'last_reply_by',
        'closed_at',
        'auto_closed_at',
        'admin_reminder_sent_at',
        'feedback_sent_at',
    ];

    protected $casts = [
        'last_reply_at' => 'datetime',
        'closed_at' => 'datetime',
        'auto_closed_at' => 'datetime',
        'admin_reminder_sent_at' => 'datetime',
        'feedback_sent_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportTicketReply::class);
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }
}
