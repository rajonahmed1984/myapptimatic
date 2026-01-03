<?php

namespace App\Jobs;

use App\Models\AnomalyFlag;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EvaluateChurnJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'ai';

    public function __construct(public int $subscriptionId)
    {
    }

    public function handle(): void
    {
        // Stub: reserved for AI churn evaluation.
    }

    public function failed(\Throwable $exception): void
    {
        AnomalyFlag::create([
            'model_type' => 'subscription',
            'model_id' => $this->subscriptionId,
            'flag_type' => 'abuse',
            'risk_score' => null,
            'summary' => 'EvaluateChurnJob failed: '.$exception->getMessage(),
            'state' => 'error',
            'metadata' => [],
        ]);
    }
}
