<?php

namespace App\Modules\Payment\Gateways;

use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlipayGateway implements PaymentGatewayInterface
{
    protected string $appId;
    protected string $privateKey;
    protected string $alipayPublicKey;
    protected string $gatewayUrl;

    public function __construct()
    {
        $this->appId = config('payment.gateways.alipay.app_id', '');
        $this->privateKey = config('payment.gateways.alipay.private_key', '');
        $this->alipayPublicKey = config('payment.gateways.alipay.public_key', '');
        $this->gatewayUrl = config('payment.gateways.alipay.gateway_url', 'https://openapi.alipay.com/gateway.do');
    }

    public function getName(): string
    {
        return 'alipay';
    }

    public function createCharge(Payment $payment, array $options = []): array
    {
        try {
            $bizContent = [
                'out_trade_no' => $payment->idempotency_key ?? "PAY-{$payment->id}-" . time(),
                'total_amount' => number_format($payment->amount, 2, '.', ''),
                'subject' => $options['description'] ?? "账单支付 #{$payment->bill_id}",
                'product_code' => 'QUICK_MSECURITY_PAY',
            ];

            $params = [
                'app_id' => $this->appId,
                'method' => 'alipay.trade.app.pay',
                'charset' => 'utf-8',
                'sign_type' => 'RSA2',
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'version' => '1.0',
                'notify_url' => config('payment.gateways.alipay.notify_url', ''),
                'biz_content' => json_encode($bizContent),
            ];

            $params['sign'] = $this->generateSign($params);

            Log::channel('payment')->info('支付宝支付创建', [
                'payment_id' => $payment->id,
                'out_trade_no' => $bizContent['out_trade_no'],
            ]);

            return [
                'gateway_payment_id' => $bizContent['out_trade_no'],
                'payment_params' => $params,
                'payment_url' => $this->gatewayUrl . '?' . http_build_query($params),
                'status' => 'pending',
            ];
        } catch (\Exception $e) {
            throw new PaymentGatewayException('alipay', $e->getMessage(), $e);
        }
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        $params = [];
        parse_str($payload, $params);

        $sign = $params['sign'] ?? '';
        unset($params['sign'], $params['sign_type']);

        ksort($params);
        $signContent = urldecode(http_build_query($params));

        $publicKey = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($this->alipayPublicKey, 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";

        return (bool) openssl_verify(
            $signContent,
            base64_decode($sign),
            $publicKey,
            OPENSSL_ALGO_SHA256
        );
    }

    public function parseWebhookPayload(string $payload): array
    {
        $params = [];
        parse_str($payload, $params);

        $tradeStatus = $params['trade_status'] ?? '';

        return [
            'event_type' => $tradeStatus,
            'gateway_payment_id' => $params['out_trade_no'] ?? null,
            'status' => $this->mapStatus($tradeStatus),
            'amount' => (float) ($params['total_amount'] ?? 0),
            'transaction_id' => $params['trade_no'] ?? null,
            'metadata' => $params,
            'raw' => $params,
        ];
    }

    public function queryPaymentStatus(string $gatewayPaymentId): array
    {
        $bizContent = ['out_trade_no' => $gatewayPaymentId];

        $params = [
            'app_id' => $this->appId,
            'method' => 'alipay.trade.query',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'version' => '1.0',
            'biz_content' => json_encode($bizContent),
        ];
        $params['sign'] = $this->generateSign($params);

        $response = Http::get($this->gatewayUrl, $params);
        $data = $response->json('alipay_trade_query_response', []);

        return [
            'status' => $this->mapStatus($data['trade_status'] ?? ''),
            'amount' => (float) ($data['total_amount'] ?? 0),
            'gateway_payment_id' => $data['out_trade_no'] ?? $gatewayPaymentId,
        ];
    }

    public function refund(string $gatewayPaymentId, float $amount, string $reason): array
    {
        $bizContent = [
            'out_trade_no' => $gatewayPaymentId,
            'refund_amount' => number_format($amount, 2, '.', ''),
            'refund_reason' => $reason,
            'out_request_no' => 'REFUND-' . $gatewayPaymentId . '-' . time(),
        ];

        $params = [
            'app_id' => $this->appId,
            'method' => 'alipay.trade.refund',
            'charset' => 'utf-8',
            'sign_type' => 'RSA2',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'version' => '1.0',
            'biz_content' => json_encode($bizContent),
        ];
        $params['sign'] = $this->generateSign($params);

        $response = Http::get($this->gatewayUrl, $params);
        $data = $response->json('alipay_trade_refund_response', []);

        if (($data['code'] ?? '') !== '10000') {
            throw new PaymentGatewayException('alipay', $data['sub_msg'] ?? '退款失败');
        }

        return [
            'gateway_refund_id' => $data['trade_no'] ?? null,
            'status' => 'completed',
            'amount' => $amount,
        ];
    }

    protected function generateSign(array $params): string
    {
        ksort($params);
        $signContent = urldecode(http_build_query($params));

        $privateKey = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($this->privateKey, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";

        openssl_sign($signContent, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }

    protected function mapStatus(string $alipayStatus): string
    {
        return match ($alipayStatus) {
            'TRADE_SUCCESS', 'TRADE_FINISHED' => 'completed',
            'WAIT_BUYER_PAY' => 'pending',
            'TRADE_CLOSED' => 'failed',
            default => 'pending',
        };
    }
}
