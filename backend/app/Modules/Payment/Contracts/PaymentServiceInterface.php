<?php

namespace App\Modules\Payment\Contracts;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\Refund;

interface PaymentServiceInterface
{
    public function createPayment(Bill $bill, string $paymentMethod, array $options = []): array;
    public function processPaymentCallback(Payment $payment, ?string $transactionId, bool $success): void;
    public function refund(Payment $payment, float $amount, string $reason, string $type = 'partial'): Refund;
    public function syncPaymentStatus(Payment $payment): Payment;
    public function handleWebhook(string $gateway, string $payload, string $signature): void;
}
