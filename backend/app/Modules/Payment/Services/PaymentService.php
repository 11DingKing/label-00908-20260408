<?php

namespace App\Modules\Payment\Services;

use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\Contracts\PaymentServiceInterface;
use App\Modules\Payment\Exceptions\DuplicatePaymentException;
use App\Modules\Payment\Exceptions\PaymentGatewayException;
use App\Modules\Payment\Exceptions\RefundException;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService implements PaymentServiceInterface
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager
    ) {}

    public function createPayment(Bill $bill, string $paymentMethod, array $options = []): array
    {
        $idempotencyKey = $options['idempotency_key'] ?? Str::uuid()->toString();
        $existing = Payment::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            throw new DuplicatePaymentException($idempotencyKey);
        }

        return DB::transaction(function () use ($bill, $paymentMethod, $options, $idempotencyKey) {
            $gateway = $this->resolveGateway($paymentMethod);
            $payment = Payment::create([
                'user_id' => $bill->user_id, 'bill_id' => $bill->id,
                'payment_method' => $paymentMethod, 'amount' => $bill->total_amount,
                'currency' => $bill->currency ?? config('payment.default_currency', 'CNY'),
                'refunded_amount' => 0, 'status' => 'pending',
                'gateway' => $gateway->getName(),
                'idempotency_key' => $idempotencyKey,
                'payment_data' => $options['payment_data'] ?? [],
            ]);

            Log::channel('payment')->info('支付订单已创建', [
                'payment_id' => $payment->id, 'bill_id' => $bill->id,
                'amount' => $payment->amount, 'gateway' => $gateway->getName(),
            ]);

            try {
                $gatewayResult = $gateway->createCharge($payment, $options);
                $payment->update([
                    'gateway_payment_id' => $gatewayResult['gateway_payment_id'] ?? null,
                    'status' => 'processing', 'gateway_response' => $gatewayResult,
                ]);
                return ['payment' => $payment->fresh(), 'gateway_data' => $gatewayResult];
            } catch (PaymentGatewayException $e) {
                $payment->update(['status' => 'failed', 'notes' => $e->getMessage()]);
                throw $e;
            }
        });
    }

    public function handleWebhook(string $gateway, string $payload, string $signature): void
    {
        $gatewayInstance = $this->gatewayManager->gateway($gateway);
        if (!$gatewayInstance->verifyWebhookSignature($payload, $signature)) {
            Log::channel('payment')->warning('Webhook签名验证失败', ['gateway' => $gateway]);
            throw new PaymentGatewayException($gateway, 'Webhook签名验证失败');
        }
        $data = $gatewayInstance->parseWebhookPayload($payload);
        Log::channel('payment')->info('收到Webhook回调', [
            'gateway' => $gateway, 'event_type' => $data['event_type'],
            'gateway_payment_id' => $data['gateway_payment_id'],
        ]);
        $payment = Payment::where('gateway_payment_id', $data['gateway_payment_id'])
            ->orWhere('idempotency_key', $data['gateway_payment_id'])->first();
        if (!$payment) {
            Log::channel('payment')->warning('Webhook找不到对应支付记录', $data);
            return;
        }
        $this->processPaymentCallback($payment, $data['transaction_id'] ?? null, $data['status'] === 'completed');
    }

    public function processPaymentCallback(Payment $payment, ?string $transactionId, bool $success): void
    {
        if ($payment->status === 'completed') return;
        if ($success) {
            $payment->markAsCompleted($transactionId);
            Log::channel('payment')->info('支付成功', ['payment_id' => $payment->id, 'transaction_id' => $transactionId]);
        } else {
            $payment->update(['status' => 'failed', 'transaction_id' => $transactionId]);
            Log::channel('payment')->warning('支付失败', ['payment_id' => $payment->id, 'transaction_id' => $transactionId]);
        }
    }

    public function refund(Payment $payment, float $amount, string $reason, string $type = 'partial'): Refund
    {
        if ($payment->status !== 'completed') {
            throw new RefundException('只能对已完成的支付发起退款');
        }
        $maxRefundable = $payment->amount - $payment->refunded_amount;
        if ($amount > $maxRefundable) {
            throw new RefundException("退款金额超出可退金额，最大可退: {$maxRefundable}");
        }
        if ($amount == $maxRefundable) $type = 'full';

        return DB::transaction(function () use ($payment, $amount, $reason, $type) {
            $refundNumber = 'REF-' . now()->format('Ymd') . '-' . str_pad(
                Refund::whereDate('created_at', today())->count() + 1, 6, '0', STR_PAD_LEFT
            );
            $refund = Refund::create([
                'payment_id' => $payment->id, 'user_id' => $payment->user_id,
                'refund_number' => $refundNumber, 'amount' => $amount,
                'currency' => $payment->currency, 'type' => $type,
                'status' => 'pending', 'reason' => $reason,
            ]);

            if ($payment->gateway_payment_id && $payment->gateway) {
                try {
                    $gateway = $this->gatewayManager->gateway($payment->gateway);
                    $result = $gateway->refund($payment->gateway_payment_id, $amount, $reason);
                    $refund->update([
                        'status' => $result['status'] === 'completed' ? 'completed' : 'processing',
                        'gateway_refund_id' => $result['gateway_refund_id'] ?? null,
                        'gateway_response' => $result,
                        'refunded_at' => $result['status'] === 'completed' ? now() : null,
                    ]);
                } catch (PaymentGatewayException $e) {
                    $refund->update(['status' => 'failed', 'notes' => $e->getMessage()]);
                    throw new RefundException("网关退款失败: {$e->getMessage()}", $e);
                }
            } else {
                $refund->markAsCompleted('LOCAL-' . Str::uuid());
            }

            $payment->increment('refunded_amount', $amount);
            if ($payment->refunded_amount >= $payment->amount) {
                $payment->update(['status' => 'refunded', 'refunded_at' => now()]);
                if ($payment->bill) {
                    $payment->bill->update(['status' => 'pending', 'paid_at' => null]);
                }
            }

            Log::channel('payment')->info('退款已处理', [
                'refund_id' => $refund->id, 'payment_id' => $payment->id,
                'amount' => $amount, 'type' => $type,
            ]);
            return $refund;
        });
    }

    public function syncPaymentStatus(Payment $payment): Payment
    {
        if (!$payment->gateway_payment_id || !$payment->gateway) return $payment;
        $gateway = $this->gatewayManager->gateway($payment->gateway);
        $result = $gateway->queryPaymentStatus($payment->gateway_payment_id);
        if ($result['status'] === 'completed' && $payment->status !== 'completed') {
            $payment->markAsCompleted($payment->transaction_id);
        } elseif ($result['status'] === 'failed' && $payment->status === 'pending') {
            $payment->update(['status' => 'failed']);
        }
        return $payment->fresh();
    }

    protected function resolveGateway(string $paymentMethod): PaymentGatewayInterface
    {
        $gatewayMap = [
            'credit_card' => 'stripe', 'alipay' => 'alipay',
            'wechat' => 'wechat', 'bank_transfer' => 'stripe',
        ];
        $gatewayName = $gatewayMap[$paymentMethod] ?? config('payment.default_gateway', 'stripe');
        return $this->gatewayManager->gateway($gatewayName);
    }
}
