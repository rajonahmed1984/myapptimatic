<?php

namespace App\Jobs;

use App\Models\AnomalyFlag;
use App\Models\License;
use App\Models\LicenseUsageLog;
use App\Services\AiLicenseRiskService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EvaluateLicenseRiskJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'ai';

    public function __construct(public int $usageLogId)
    {
    }

    public function handle(): void
    {
        $usage = LicenseUsageLog::find($this->usageLogId);
        if (! $usage) {
            return;
        }

        if (! config('ai.enabled') || ! config('ai.license_risk_enabled')) {
            return;
        }

        $license = $usage->license_id ? License::find($usage->license_id) : null;
        $payload = [
            'request_id' => $usage->request_id,
            'license_id' => $usage->license_id,
            'subscription_id' => $usage->subscription_id,
            'customer_id' => $usage->customer_id,
            'license_key' => $license?->license_key,
            'domain' => $usage->domain,
            'ip' => $usage->ip,
            'user_agent' => $usage->user_agent,
            'decision' => $usage->decision,
            'reason' => $usage->reason,
            'metadata' => $usage->metadata,
            'recorded_at' => $usage->created_at?->toIso8601String(),
        ];

        $result = app(AiLicenseRiskService::class)->score($payload);

        if ($license) {
            $license->update([
                'last_risk_score' => $result['risk_score'] ?? null,
                'last_risk_reason' => $result['reason'] ?? null,
            ]);
        }

        $shouldFlag = in_array($result['decision'] ?? 'allow', ['warn', 'block'], true);
        if ($shouldFlag) {
            AnomalyFlag::create([
                'model_type' => License::class,
                'model_id' => $license?->id ?? 0,
                'flag_type' => 'abuse',
                'risk_score' => $result['risk_score'] ?? null,
                'summary' => $result['reason'] ?? 'license_risk',
                'state' => 'open',
                'metadata' => [
                    'decision' => $result['decision'] ?? null,
                    'details' => $result['details'] ?? null,
                    'request_id' => $usage->request_id,
                ],
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Log an anomaly flag to avoid silent drops.
        AnomalyFlag::create([
            'model_type' => LicenseUsageLog::class,
            'model_id' => $this->usageLogId,
            'flag_type' => 'abuse',
            'risk_score' => null,
            'summary' => 'EvaluateLicenseRiskJob failed: '.$exception->getMessage(),
            'state' => 'error',
            'metadata' => [],
        ]);
    }
}
