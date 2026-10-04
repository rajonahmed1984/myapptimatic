<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\CommissionEarning;
use App\Models\CommissionPayout;
use App\Models\Concerns\HasActivityTracking;
use App\Models\ProjectMaintenance;
use App\Models\Subscription;
use App\Support\UrlResolver;
use Illuminate\Support\Str;

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
        'referral_code',
        'payout_method_default',
        'payout_details_encrypted',
        'metadata',
        'avatar_path',
        'nid_path',
        'cv_path',
        'project_commission_percentage',
        'subscription_commission_percentage',
    ];

    protected $casts = [
        'payout_details_encrypted' => 'array',
        'metadata' => 'array',
    ];

    /** Self-registered, waiting for an admin to approve. */
    public const STATUS_PENDING = 'pending';

    public const STATUSES = ['active', 'inactive', self::STATUS_PENDING];

    protected static function booted(): void
    {
        static::creating(function (SalesRepresentative $rep) {
            if (! $rep->referral_code) {
                $rep->referral_code = static::generateReferralCode();
            }
        });
    }

    public static function generateReferralCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (static::query()->where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * Where to send people: sign-up with this rep's code. The code also works
     * on any other page of the site (?ref=CODE).
     */
    public function referralUrl(): string
    {
        return rtrim(UrlResolver::portalUrl(), '/').'/register?ref='.urlencode((string) $this->referral_code);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function referredCustomers(): HasMany
    {
        return $this->hasMany(Customer::class, 'referred_by_sales_rep_id');
    }

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

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'sales_rep_id');
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_sales_representative')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function maintenances(): BelongsToMany
    {
        return $this->belongsToMany(
            ProjectMaintenance::class,
            'project_maintenance_sales_representative',
            'sales_representative_id',
            'project_maintenance_id'
        )->withPivot('amount')
            ->withTimestamps();
    }
}
