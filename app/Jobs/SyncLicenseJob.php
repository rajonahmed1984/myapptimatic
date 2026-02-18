<?php

namespace App\Jobs;

use App\Models\License;
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

    public function handle(): void
    {
        $license = License::find($this->licenseId);

        if (! $license) {
            return;
        }

        $license->update([
            'last_check_at' => now(),
            'last_verified_at' => now(),
            'last_check_ip' => $this->ipAddress,
        ]);
    }
}
