<?php

namespace App\Observers;

use App\Models\AccountingEntry;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Subscription;
use App\Services\MyBuildingLicenseSync;
use Illuminate\Database\Eloquent\Model;

/**
 * Notices every change that alters what a MyBuilding installation should
 * enforce, wherever it comes from (admin edit, billing:run, auto-suspend,
 * payment callback), and has the licence state pushed there.
 */
class MyBuildingLicenseSyncObserver
{
    private const WATCHED = [
        License::class => ['status', 'expires_at', 'auto_suspend_override_until', 'subscription_id'],
        Subscription::class => ['status', 'customer_id'],
        Customer::class => ['status', 'access_override_until'],
        Invoice::class => ['status', 'total', 'due_date', 'subscription_id'],
        AccountingEntry::class => ['amount', 'type', 'invoice_id'],
    ];

    public function __construct(private readonly MyBuildingLicenseSync $sync) {}

    public function created(Model $model): void
    {
        // A new licence has no building yet, and a new subscription no licence.
        if ($model instanceof Invoice || $model instanceof AccountingEntry) {
            $this->queueFor($model);
        }
    }

    public function updated(Model $model): void
    {
        $watched = self::WATCHED[$model::class] ?? [];

        if ($watched !== [] && $model->wasChanged($watched)) {
            $this->queueFor($model);

            // Moving an invoice or licence to another subscription affects
            // the one it left as well.
            if ($model->wasChanged('subscription_id') && $model->getOriginal('subscription_id')) {
                $this->sync->queueSubscription((int) $model->getOriginal('subscription_id'));
            }
        }
    }

    public function deleting(Model $model): void
    {
        if ($model instanceof License) {
            $this->sync->queueRemoval($model);

            return;
        }

        if ($model instanceof Subscription) {
            // Licences cascade away with the subscription, so their buildings
            // have to be told now, while the keys are still readable.
            $model->licenses()->get()->each(fn (License $license) => $this->sync->queueRemoval($license));
        }
    }

    public function deleted(Model $model): void
    {
        if ($model instanceof Invoice || $model instanceof AccountingEntry) {
            $this->queueFor($model);
        }
    }

    private function queueFor(Model $model): void
    {
        match (true) {
            $model instanceof License => $this->sync->queueLicense((int) $model->id),
            $model instanceof Subscription => $this->sync->queueSubscription((int) $model->id),
            $model instanceof Customer => $this->sync->queueCustomer((int) $model->id),
            $model instanceof Invoice => $this->sync->queueSubscription($model->subscription_id ? (int) $model->subscription_id : null),
            $model instanceof AccountingEntry => $this->sync->queueSubscription(
                $model->invoice_id
                    ? (int) (Invoice::query()->whereKey($model->invoice_id)->value('subscription_id') ?? 0) ?: null
                    : null
            ),
            default => null,
        };
    }
}
