<?php

namespace App\Modules\Payment\Gateways;

use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WechatPayGateway implements PaymentGatewayInterface
{
    protected string $appId;
    protected string $mchId;
    protected string $apiKey;
    protected string $baseUrl = 'https://api.mch.weixin.qq.com/v3';

    public function __construct()
    {
        $this->appId = config('payment.gateways.wechat.app_id', '');
        $this->mchId = config('payment.gateways.wechat.mch_id', '');
        $this->apiKey = config('payment.gateways.wechat.api_key', '');
    }

    public function getName(): string
    {
        return 'wechat';
    }

    public function createCharge(Payment $payment, array $options = []): array
    {
        try {
            $outTradeNo = $payment->idempotency_key ?? "PAY-{$payment->id}-" . time();

            $body = [
                'appid' => $this->appId,
                'mchid' => $this->mchId,
                'description' => $options['description'] ?? "账单支付 #{$payment->bill_id}",
                'out_trade_no' => $outTradeNo,
                'notify_url' => config('payment.gateways.wechat.notify_url', ''),
                'amount' => [
                    'total' => (int) ($payment->amount * 100), // 微信用分
                    'currency' => strtoupper($payment->currency ?? 'CNY'),
                ],
            ];

            $response = Http::withHeaders($this->buildAuthHeaders('POST', '/v3/pay/transactions/native', $body))
                ->post("{$this->baseUrl}/pay/transactions/native", $body);

            if ($response->failed()) {
                throw new PaymentGatewayException('wechat', $response->json('message', '创建支付失败'));
            }

            $data = $response->json();

            Log::channel('payment')->info('微信支付创建成功', [
                'payment_id' => $payment->id,
                'out_trade_no' => $outTradeNo,
            ]);

            return [
                'gateway_payment_id' => $outTradeNo,
                'code_url' => $data['code_url'] ?? null,
                'status' => 'pending',
            ];
        } catch (PaymentGatewayException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new PaymentGatewayException('wechat', $e->getMessage(), $e);
        }
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        // 微信V3使用AEAD_AES_256_GCM解密
        // 简化实现：验证签名头
        $computedSig = hash_hmac('sha256', $payload, $this->apiKey);
        return hash_equals($computedSig, $signature);
    }

    public function parseWebhookPayload(string $payload): array
    {
        $data = json_decode($payload, true);
        $resource = $data['resource'] ?? [];

        // 解密resource（简化）
        $decrypted = $resource;

        return [
            'event_type' => $data['event_type'] ?? 'unknown',
            'gateway_payment_id' => $decrypted['out_trade_no'] ?? null,
            'status' => $this->mapStatus($decrypted['trade_state'] ?? ''),
            'amount' => isset($decrypted['amount']['total']) ? $decrypted['amount']['total'] / 100 : 0,
            'transaction_id' => $decrypted['transaction_id'] ?? null,
            'metadata' => $decrypted,
            'raw' => $data,
        ];
    }

    public function queryPaymentStatus(string $gatewayPaymentId): array
    {
        $url = "{$this->baseUrl}/pay/transactions/out-trade-no/{$gatewayPaymentId}?mchid={$this->mchId}";

        $response = Http::withHeaders($this->buildAuthHeaders('GET', "/v3/pay/transactions/out-trade-no/{$gatewayPaymentId}?mchid={$this->mchId}"))
            ->get($url);

        if ($response->failed()) {
            throw new PaymentGatewayException('wechat', '查询支付状态失败');
        }

        $data = $response->json();
        return [
            'status' => $this->mapStatus($data['trade_state'] ?? ''),
            'amount' => isset($data['amount']['total']) ? $data['amount']['total'] / 100 : 0,
            'gateway_payment_id' => $data['out_trade_no'] ?? $gatewayPaymentId,
        ];
    }

    public function refund(string $gatewayPaymentId, float $amount, string $reason): array
    {
        $body = [
            'out_trade_no' => $gatewayPaymentId,
            'out_refund_no' => 'REFUND-' . $gatewayPaymentId . '-' . time(),
            'reason' => $reason,
            'amount' => [
                'refund' => (int) ($amount * 100),
                'total' => (int) ($amount * 100),
                'currency' => 'CNY',
            ],
        ];

        $response = Http::withHeaders($this->buildAuthHeaders('POST', '/v3/refund/domestic/refunds', $body))
            ->post("{$this->baseUrl}/refund/domestic/refunds", $body);

        if ($response->failed()) {
            throw new PaymentGatewayException('wechat', $response->json('message', '退款失败'));
        }

        $data = $response->json();
        return [
            'gateway_refund_id' => $data['refund_id'] ?? null,
            'status' => $this->mapRefundStatus($data['status'] ?? ''),
            'amount' => $amount,
        ];
    }

    protected function buildAuthHeaders(string $method, string $url, array $body = []): array
    {
        $timestamp = time();
        $nonce = bin2hex(random_bytes(16));
        $bodyStr = !empty($body) ? json_encode($body) : '';
        $message = "{$method}\n{$url}\n{$timestamp}\n{$nonce}\n{$bodyStr}\n";
        $signature = hash_hmac('sha256', $message, $this->apiKey);

        return [
            'Authorization' => "WECHATPAY2-SHA256-RSA2048 mchid=\"{$this->mchId}\",nonce_str=\"{$nonce}\",timestamp=\"{$timestamp}\",signature=\"{$signature}\"",
            'Content-Type' => 'application/json',
        ];
    }

    protected function mapStatus(string $status): string
    {
        return match ($status) {
            'SUCCESS' => 'completed',
            'NOTPAY', 'USERPAYING' => 'pending',
            'CLOSED', 'PAYERROR', 'REVOKED' => 'failed',
            default => 'pending',
        };
    }

    protected function mapRefundStatus(string $status): string
    {
        return match ($status) {
            'SUCCESS' => 'completed',
            'PROCESSING' => 'processing',
            default => 'pending',
        };
    }
}
