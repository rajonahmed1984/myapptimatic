<?php

namespace App\Jobs;

use App\Models\SyncHealthLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecordSyncHealthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $licenseId,
        public ?int $licenseDomainId = null,
        public array $payload = []
    ) {
        $this->onQueue('ai');
    }

    public function handle(): void
    {
        SyncHealthLog::create([
            'license_id' => $this->licenseId,
            'license_domain_id' => $this->licenseDomainId,
            'status' => $this->payload['status'] ?? 'success',
            'latency_ms' => $this->payload['latency_ms'] ?? null,
            'http_status' => $this->payload['http_status'] ?? null,
            'retries' => $this->payload['retries'] ?? 0,
            'source' => $this->payload['source'] ?? 'api',
            'message' => $this->payload['message'] ?? null,
        ]);
    }
}
