<?php

namespace App\Modules\Payment\Gateways;

use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\Exceptions\PaymentDeclinedException;
use App\Modules\Payment\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\Webhook;

/**
 * Stripe 支付网关（使用官方 SDK）
 * 
 * @see https://stripe.com/docs/api
 */
class StripeGateway implements PaymentGatewayInterface
{
    protected string $webhookSecret;

    public function __construct()
    {
        Stripe::setApiKey(config('payment.gateways.stripe.secret_key', ''));
        $this->webhookSecret = config('payment.gateways.stripe.webhook_secret', '');
    }

    public function getName(): string
    {
        return 'stripe';
    }

    public function createCharge(Payment $payment, array $options = []): array
    {
        try {
            $paymentIntent = PaymentIntent::create([
                'amount' => (int) ($payment->amount * 100), // Stripe 使用分
                'currency' => strtolower($payment->currency ?? 'cny'),
                'payment_method_types' => ['card'],
                'metadata' => [
                    'payment_id' => $payment->id,
                    'bill_id' => $payment->bill_id,
                    'user_id' => $payment->user_id,
                ],
                'description' => $options['description'] ?? "Payment #{$payment->id}",
            ], [
                'idempotency_key' => $payment->idempotency_key,
            ]);

            Log::channel('payment')->info('Stripe支付创建成功', [
                'payment_id' => $payment->id,
                'stripe_id' => $paymentIntent->id,
            ]);

            return [
                'gateway_payment_id' => $paymentIntent->id,
                'client_secret' => $paymentIntent->client_secret,
                'status' => $this->mapStatus($paymentIntent->status),
                'payment_url' => $options['return_url'] ?? null,
            ];
        } catch (CardException $e) {
            Log::channel('payment')->warning('Stripe支付被拒绝', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'decline_code' => $e->getDeclineCode(),
            ]);
            throw new PaymentDeclinedException('支付被拒绝', $e->getMessage(), $e);
        } catch (ApiErrorException $e) {
            Log::channel('payment')->error('Stripe API错误', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            throw new PaymentGatewayException('stripe', $e->getMessage(), $e);
        }
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        try {
            Webhook::constructEvent($payload, $signature, $this->webhookSecret);
            return true;
        } catch (\Exception $e) {
            Log::channel('payment')->warning('Stripe Webhook签名验证失败', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function parseWebhookPayload(string $payload): array
    {
        $data = json_decode($payload, true);
        $object = $data['data']['object'] ?? [];

        return [
            'event_type' => $data['type'] ?? 'unknown',
            'gateway_payment_id' => $object['id'] ?? null,
            'status' => $this->mapStatus($object['status'] ?? ''),
            'amount' => isset($object['amount']) ? $object['amount'] / 100 : 0,
            'transaction_id' => $object['latest_charge'] ?? $object['id'] ?? null,
            'metadata' => $object['metadata'] ?? [],
            'raw' => $data,
        ];
    }

    public function queryPaymentStatus(string $gatewayPaymentId): array
    {
        try {
            $paymentIntent = PaymentIntent::retrieve($gatewayPaymentId);

            return [
                'status' => $this->mapStatus($paymentIntent->status),
                'amount' => $paymentIntent->amount / 100,
                'gateway_payment_id' => $paymentIntent->id,
            ];
        } catch (ApiErrorException $e) {
            throw new PaymentGatewayException('stripe', '查询支付状态失败: ' . $e->getMessage(), $e);
        }
    }

    public function refund(string $gatewayPaymentId, float $amount, string $reason): array
    {
        try {
            $refund = Refund::create([
                'payment_intent' => $gatewayPaymentId,
                'amount' => (int) ($amount * 100),
                'reason' => 'requested_by_customer',
                'metadata' => ['reason' => $reason],
            ]);

            Log::channel('payment')->info('Stripe退款成功', [
                'refund_id' => $refund->id,
                'payment_intent' => $gatewayPaymentId,
                'amount' => $amount,
            ]);

            return [
                'gateway_refund_id' => $refund->id,
                'status' => $refund->status === 'succeeded' ? 'completed' : 'processing',
                'amount' => $refund->amount / 100,
            ];
        } catch (ApiErrorException $e) {
            Log::channel('payment')->error('Stripe退款失败', [
                'payment_intent' => $gatewayPaymentId,
                'error' => $e->getMessage(),
            ]);
            throw new PaymentGatewayException('stripe', '退款失败: ' . $e->getMessage(), $e);
        }
    }

    protected function mapStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'succeeded' => 'completed',
            'processing' => 'processing',
            'requires_payment_method', 'requires_confirmation', 'requires_action' => 'pending',
            'canceled' => 'failed',
            default => 'pending',
        };
    }
}
