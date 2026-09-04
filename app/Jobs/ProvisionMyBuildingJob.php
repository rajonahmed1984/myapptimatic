<?php

namespace App\Jobs;

use App\Models\MyBuildingProvision;
use App\Services\MyBuildingProvisioner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Creates the building inside the customer's MyBuilding installation.
 *
 * Runs on the queue so accepting an order never waits on a remote server, and
 * retries with a growing delay so a brief outage at the customer's end resolves
 * itself instead of needing someone to press Retry.
 */
class ProvisionMyBuildingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** 1 min, 5 min, 15 min, 1 hour. */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public int $provisionId)
    {
        $this->onQueue('provisioning');
    }

    public function handle(MyBuildingProvisioner $provisioner): void
    {
        $provision = MyBuildingProvision::with(['license', 'customer'])->find($this->provisionId);

        if (!$provision || $provision->isProvisioned()) {
            return;
        }

        if (!$provisioner->configured()) {
            // Nothing to retry against; the admin page reports the missing secret.
            $this->fail(new \RuntimeException('MYBUILDING_PROVISION_SECRET is not configured.'));

            return;
        }

        if ($provisioner->provision($provision)) {
            return;
        }

        // provision() already recorded the reason; throwing lets the queue
        // apply the backoff and try again.
        throw new \RuntimeException(
            $provision->fresh()->last_error ?? 'MyBuilding provisioning failed.'
        );
    }

    /**
     * All retries exhausted - leave a clear trail for the admin page.
     */
    public function failed(\Throwable $exception): void
    {
        $provision = MyBuildingProvision::find($this->provisionId);

        if ($provision && !$provision->isProvisioned()) {
            $provision->forceFill([
                'status' => MyBuildingProvision::STATUS_FAILED,
                'last_error' => 'Gave up after ' . $this->tries . ' attempts: ' . $exception->getMessage(),
            ])->save();
        }
    }
}
