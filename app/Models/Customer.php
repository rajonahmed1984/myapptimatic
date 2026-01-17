<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Enums\Role;
use App\Models\SalesRepresentative;
use App\Models\User;
use App\Models\Concerns\HasActivityTracking;

class Customer extends Model
{
    use HasActivityTracking;

    protected $fillable = [
        'name',
        'company_name',
        'email',
        'phone',
        'address',
        'status',
        'default_sales_rep_id',
        'access_override_until',
        'referred_by_affiliate_id',
        'notes',
        'avatar_path',
        'nid_path',
        'cv_path',
    ];

    protected $casts = [
        'access_override_until' => 'datetime',
    ];

    public function getDisplayNameAttribute(): string
    {
        $company = trim((string) ($this->company_name ?? ''));
        if ($company === '') {
            return $this->name;
        }

        return "{$this->name} - {$company}";
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function projectUsers(): HasMany
    {
        return $this->hasMany(User::class)
            ->where('role', Role::CLIENT_PROJECT)
            ->whereNotNull('project_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function licenses(): HasManyThrough
    {
        return $this->hasManyThrough(License::class, Subscription::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function defaultSalesRep(): BelongsTo
    {
        return $this->belongsTo(SalesRepresentative::class, 'default_sales_rep_id');
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

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function projectMaintenances(): HasMany
    {
        return $this->hasMany(ProjectMaintenance::class);
    }

    public function clientRequests(): HasMany
    {
        return $this->hasMany(ClientRequest::class);
    }

    public function affiliate(): HasOne
    {
        return $this->hasOne(Affiliate::class);
    }

    public function referredByAffiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'referred_by_affiliate_id');
    }
}
