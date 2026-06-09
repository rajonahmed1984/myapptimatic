<?php

namespace App\Services\Payment\Gateways\Manual;

use App\Models\PaymentAttempt;
use App\Services\Payment\Gateways\GatewayDriverInterface;

class ManualGateway implements GatewayDriverInterface
{
    public function start(PaymentAttempt $attempt): array
    {
        return [
            'status' => 'manual',
            'message' => 'Manual payment instructions provided.',
        ];
    }

    public function confirm(PaymentAttempt $attempt, array $payload): bool
    {
        return false;
    }

    public function refund(PaymentAttempt $attempt, float $amount, string $reason): array
    {
        return [
            'status' => 'error',
            'message' => 'Manual refund is not supported.',
        ];
    }
}
