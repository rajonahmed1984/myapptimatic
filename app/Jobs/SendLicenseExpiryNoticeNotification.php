<?php

namespace App\Jobs;

use App\Models\License;
use App\Services\ClientNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendLicenseExpiryNoticeNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $licenseId,
        public string $templateKey
    ) {
    }

    public function handle(ClientNotificationService $clientNotifications): void
    {
        $license = License::find($this->licenseId);

        if (! $license) {
            return;
        }

        $clientNotifications->sendLicenseExpiryNotice($license, $this->templateKey);
    }
}
