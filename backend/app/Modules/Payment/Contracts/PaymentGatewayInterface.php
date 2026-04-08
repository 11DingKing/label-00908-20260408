<?php

namespace App\Modules\Payment\Contracts;

use App\Models\Payment;

interface PaymentGatewayInterface
{
    public function createCharge(Payment $payment, array $options = []): array;
    public function verifyWebhookSignature(string $payload, string $signature): bool;
    public function parseWebhookPayload(string $payload): array;
    public function queryPaymentStatus(string $gatewayPaymentId): array;
    public function refund(string $gatewayPaymentId, float $amount, string $reason): array;
    public function getName(): string;
}
