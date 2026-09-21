<?php

namespace App\Jobs;

use App\Services\MyBuildingLicenseSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Retries a licence-state push that failed when it was first sent, so a short
 * outage at the installation does not leave a suspended building running (or
 * a paid one blocked). The state is read fresh on every attempt.
 */
class PushMyBuildingLicenseStateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;

    /** 1 min, 5 min, 15 min, 1 hour, 3 hours. */
    public array $backoff = [60, 300, 900, 3600, 10800];

    public function __construct(public int $licenseId) {}

    public function handle(MyBuildingLicenseSync $sync): void
    {
        if (! $sync->pushLicenseId($this->licenseId)) {
            throw new \RuntimeException('MyBuilding installation did not accept the licence state for licence #'.$this->licenseId.'.');
        }
    }
}
