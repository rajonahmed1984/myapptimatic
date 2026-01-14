<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Project;
use App\Models\ProjectMaintenance;

class Invoice extends Model
{
    protected $fillable = [
        'customer_id',
        'subscription_id',
        'project_id',
        'maintenance_id',
        'number',
        'status',
        'issue_date',
        'due_date',
        'paid_at',
        'overdue_at',
        'reminder_sent_at',
        'first_overdue_reminder_sent_at',
        'second_overdue_reminder_sent_at',
        'third_overdue_reminder_sent_at',
        'subtotal',
        'late_fee',
        'total',
        'currency',
        'late_fee_applied_at',
        'notes',
        'type',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'overdue_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'first_overdue_reminder_sent_at' => 'datetime',
        'second_overdue_reminder_sent_at' => 'datetime',
        'third_overdue_reminder_sent_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'late_fee' => 'decimal:2',
        'total' => 'decimal:2',
        'late_fee_applied_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(ProjectMaintenance::class, 'maintenance_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function accountingEntries(): HasMany
    {
        return $this->hasMany(AccountingEntry::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function paymentProofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function clientRequests(): HasMany
    {
        return $this->hasMany(ClientRequest::class);
    }
}
