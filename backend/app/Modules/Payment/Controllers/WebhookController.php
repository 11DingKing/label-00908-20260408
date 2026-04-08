<?php

namespace App\Modules\Payment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payment\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Stripe Webhook
     */
    public function stripe(Request $request): JsonResponse
    {
        return $this->handleWebhook('stripe', $request);
    }

    /**
     * 支付宝异步通知
     */
    public function alipay(Request $request): JsonResponse
    {
        return $this->handleWebhook('alipay', $request);
    }

    /**
     * 微信支付回调
     */
    public function wechat(Request $request): JsonResponse
    {
        return $this->handleWebhook('wechat', $request);
    }

    protected function handleWebhook(string $gateway, Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature')
            ?? $request->header('Wechatpay-Signature')
            ?? $request->input('sign', '');

        try {
            $this->paymentService->handleWebhook($gateway, $payload, $signature);

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::channel('payment')->error("Webhook处理失败 [{$gateway}]", [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }
}
