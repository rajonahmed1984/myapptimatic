<?php

namespace App\Jobs;

use App\Models\License;
use App\Services\LicenseRealtimeCheckService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncLicenseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $licenseId,
        public ?string $ipAddress
    ) {
    }

    public function handle(LicenseRealtimeCheckService $licenseRealtimeCheckService): void
    {
        $license = License::query()
            ->with(['subscription.customer', 'domains'])
            ->find($this->licenseId);

        if (! $license) {
            return;
        }

        $licenseRealtimeCheckService->sync($license, $this->ipAddress);
    }
}
