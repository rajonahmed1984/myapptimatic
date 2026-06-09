<?php

namespace App\Services\Payment\Gateways;

use App\Models\PaymentAttempt;

interface GatewayDriverInterface
{
    /**
     * Start/initiate the payment attempt.
     */
    public function start(PaymentAttempt $attempt): array;

    /**
     * Confirm/verify/execute a payment callback request.
     */
    public function confirm(PaymentAttempt $attempt, array $payload): bool;

    /**
     * Process refund if supported.
     */
    public function refund(PaymentAttempt $attempt, float $amount, string $reason): array;
}
