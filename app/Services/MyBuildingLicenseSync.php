<?php

namespace App\Services;

use App\Jobs\PushMyBuildingLicenseStateJob;
use App\Models\Invoice;
use App\Models\License;
use App\Models\MyBuildingProvision;
use App\Models\Setting;
use App\Support\LicenseInvoiceGrace;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps a customer's MyBuilding installation in step with the licence and
 * billing state held here.
 *
 * MyBuilding hosts many buildings under one installation, so its own licence
 * check cannot see a single building's suspension. Instead every change that
 * matters (licence/subscription status, a new or settled invoice, a customer
 * being deactivated) is pushed to POST {install}/api/v1/external/subscription.
 *
 * Pushes are collected during the request or command and sent once when it
 * finishes, so a controller that touches the subscription, its licences and
 * its invoices in one go still sends a single, final state. A push that fails
 * is handed to the queue to retry.
 */
class MyBuildingLicenseSync
{
    /** @var array<int, true> licence ids waiting to be pushed */
    private array $pending = [];

    /** @var array<string, array{install_url: string, payload: array}> explicit pushes (e.g. deleted licences) */
    private array $pendingPayloads = [];

    private bool $flushRegistered = false;

    public function __construct(private readonly MyBuildingProvisioner $provisioner) {}

    /**
     * Push this licence's state once the current request/command is done.
     */
    public function queueLicense(int $licenseId): void
    {
        if (! $this->provisioner->configured()) {
            return;
        }

        $this->pending[$licenseId] = true;
        $this->registerFlush();
    }

    /**
     * @param  iterable<int>  $licenseIds
     */
    public function queueLicenses(iterable $licenseIds): void
    {
        foreach ($licenseIds as $licenseId) {
            $this->queueLicense((int) $licenseId);
        }
    }

    public function queueSubscription(?int $subscriptionId): void
    {
        if (! $subscriptionId || ! $this->provisioner->configured()) {
            return;
        }

        $this->queueLicenses($this->syncableLicenseIds(
            License::query()->where('subscription_id', $subscriptionId)
        ));
    }

    public function queueCustomer(?int $customerId): void
    {
        if (! $customerId || ! $this->provisioner->configured()) {
            return;
        }

        $this->queueLicenses($this->syncableLicenseIds(
            License::query()->whereHas('subscription', fn ($q) => $q->where('customer_id', $customerId))
        ));
    }

    /**
     * The licence is about to disappear (its subscription or itself is being
     * deleted), so capture where it lives now and tell the installation it
     * has been cancelled.
     */
    public function queueRemoval(License $license): void
    {
        if (! $this->provisioner->configured()) {
            return;
        }

        $installUrl = $this->installUrlFor($license);

        if (! $installUrl) {
            return;
        }

        unset($this->pending[$license->id]);

        $this->pendingPayloads[$license->license_key] = [
            'install_url' => $installUrl,
            'payload' => [
                'license_key' => $license->license_key,
                'subscription_status' => 'cancelled',
                'renews_at' => null,
                'grace_period_ends_at' => null,
                'due_amount' => 0,
            ],
        ];

        $this->registerFlush();
    }

    /**
     * Send everything collected so far. Runs automatically when the request
     * or command terminates.
     */
    public function flush(): void
    {
        $licenseIds = array_keys($this->pending);
        $payloads = $this->pendingPayloads;

        $this->pending = [];
        $this->pendingPayloads = [];
        $this->flushRegistered = false;

        foreach ($licenseIds as $licenseId) {
            try {
                if (! $this->pushLicenseId($licenseId)) {
                    PushMyBuildingLicenseStateJob::dispatch($licenseId)->delay(now()->addMinute());
                }
            } catch (Throwable $exception) {
                Log::warning('MyBuilding licence push could not be scheduled.', [
                    'license_id' => $licenseId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        foreach ($payloads as $item) {
            try {
                $this->send($item['install_url'], $item['payload']);
            } catch (Throwable $exception) {
                Log::warning('MyBuilding cancellation push failed.', [
                    'license_key' => $item['payload']['license_key'] ?? null,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * Push one licence now. True when the installation accepted it, or when
     * there is nothing to push (not a MyBuilding licence, building not there).
     */
    public function pushLicenseId(int $licenseId): bool
    {
        $license = License::query()
            ->with(['subscription.customer', 'product'])
            ->find($licenseId);

        if (! $license) {
            return true;
        }

        return $this->push($license);
    }

    public function push(License $license): bool
    {
        if (! $this->provisioner->configured()) {
            return true;
        }

        $installUrl = $this->installUrlFor($license);

        if (! $installUrl) {
            return true;
        }

        return $this->send($installUrl, $this->state($license));
    }

    /**
     * The state the installation should hold for this licence, in the shape
     * its /external/subscription endpoint takes.
     */
    public function state(License $license): array
    {
        $license->loadMissing(['subscription.customer']);

        $subscription = $license->subscription;
        $customer = $subscription?->customer;
        $today = Carbon::today();

        $overrideUntil = $license->auto_suspend_override_until;
        $overrideActive = $overrideUntil && Carbon::now()->lessThanOrEqualTo($overrideUntil->copy()->endOfDay());
        $customerOverride = $customer?->access_override_until && $customer->access_override_until->isFuture();

        $licenseStatus = (string) $license->status;
        $subscriptionStatus = (string) ($subscription?->status ?? '');

        $status = 'active';

        if (! $subscription || $subscriptionStatus === 'cancelled' || $licenseStatus === 'revoked') {
            $status = 'cancelled';
        } elseif ($customer && (string) $customer->status !== 'active') {
            $status = 'suspended';
        } elseif (
            ($licenseStatus === 'suspended' || $subscriptionStatus === 'suspended')
            && ! $overrideActive
        ) {
            $status = 'suspended';
        } elseif ($licenseStatus === 'expired' || ($license->expires_at && $license->expires_at->lt($today))) {
            $status = 'expired';
        } elseif (! in_array($licenseStatus, ['active', 'suspended'], true)) {
            $status = 'suspended';
        }

        $openInvoices = $subscription
            ? Invoice::query()
                ->where('subscription_id', $subscription->id)
                ->whereIn('status', ['unpaid', 'overdue'])
                ->withSum(['accountingEntries as collected' => fn ($q) => $q->whereIn('type', ['payment', 'credit'])], 'amount')
                ->orderBy('due_date')
                ->get()
            : collect();

        $dueAmount = round($openInvoices->sum(
            fn (Invoice $invoice) => max(0, (float) $invoice->total - (float) ($invoice->collected ?? 0))
        ), 2);

        // When the installation should expect the building to be cut off if
        // nothing is paid. Only a future date is sent: the actual suspension
        // is decided here and pushed, not inferred over there.
        $graceEndsAt = null;
        if ($status === 'active' && $dueAmount > 0) {
            if ($overrideActive) {
                $graceEndsAt = $overrideUntil->copy();
            } elseif ($customerOverride) {
                $graceEndsAt = $customer->access_override_until->copy();
            } elseif (! LicenseInvoiceGrace::hasEnded()) {
                $graceEndsAt = Carbon::parse(LicenseInvoiceGrace::cutoff()->toDateString());
            } else {
                $graceDays = (int) Setting::getValue('grace_period_days');
                $firstDue = $openInvoices->first()?->due_date;
                $graceEndsAt = $firstDue ? Carbon::parse($firstDue)->addDays($graceDays) : null;
            }

            if ($graceEndsAt && $graceEndsAt->endOfDay()->isPast()) {
                $graceEndsAt = null;
            }
        }

        return [
            'license_key' => $license->license_key,
            'subscription_status' => $status,
            'renews_at' => $license->expires_at?->toDateString(),
            'grace_period_ends_at' => $graceEndsAt?->toDateString(),
            'due_amount' => $dueAmount,
            'currency' => $openInvoices->first()?->currency ?: 'BDT',
        ];
    }

    /**
     * Where this licence's building lives, or null when it is not a
     * MyBuilding licence that has been handed over.
     */
    public function installUrlFor(License $license): ?string
    {
        $provision = MyBuildingProvision::query()
            ->where('license_id', $license->id)
            ->first();

        if ($provision) {
            return $provision->isProvisioned() && $provision->install_url
                ? $provision->install_url
                : null;
        }

        // A building keyed in by hand on the installation has no provision
        // row here; the product still says where it lives.
        $license->loadMissing('product');

        if ($license->product?->slug === config('mybuilding.product_slug')) {
            return config('mybuilding.default_install_url') ?: null;
        }

        return null;
    }

    private function send(string $installUrl, array $payload): bool
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $this->provisioner->secret());

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Apptimatic-Timestamp' => $timestamp,
                'X-Apptimatic-Signature' => $signature,
            ])
                ->timeout((int) config('mybuilding.timeout', 20))
                ->withBody($body, 'application/json')
                ->post(rtrim($installUrl, '/').'/api/v1/external/subscription');
        } catch (Throwable $exception) {
            Log::warning('MyBuilding licence push failed: installation unreachable.', [
                'license_key' => $payload['license_key'] ?? null,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        if ($response->successful()) {
            Log::info('MyBuilding licence state pushed.', [
                'license_key' => $payload['license_key'] ?? null,
                'status' => $payload['subscription_status'] ?? null,
                'due_amount' => $payload['due_amount'] ?? null,
            ]);

            return true;
        }

        // 422 means the installation does not know this key (the building was
        // never created there); retrying cannot change that.
        if ($response->status() === 422 || $response->status() === 404) {
            Log::info('MyBuilding licence push skipped: key not on the installation.', [
                'license_key' => $payload['license_key'] ?? null,
                'status' => $response->status(),
            ]);

            return true;
        }

        Log::warning('MyBuilding licence push rejected.', [
            'license_key' => $payload['license_key'] ?? null,
            'status' => $response->status(),
            'body' => mb_substr((string) $response->body(), 0, 500),
        ]);

        return false;
    }

    /**
     * @return array<int>
     */
    private function syncableLicenseIds($licenseQuery): array
    {
        $productSlug = config('mybuilding.product_slug');

        return $licenseQuery
            ->where(function ($q) use ($productSlug) {
                $q->whereIn('id', MyBuildingProvision::query()
                    ->where('status', MyBuildingProvision::STATUS_PROVISIONED)
                    ->select('license_id'))
                    ->orWhereHas('product', fn ($p) => $p->where('slug', $productSlug));
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function registerFlush(): void
    {
        if ($this->flushRegistered) {
            return;
        }

        $this->flushRegistered = true;

        app()->terminating(fn () => $this->flush());
    }
}
