<?php

/**
 * Payment Module Routes
 * 支付模块路由 - 支付、退款和Webhook
 */

use App\Modules\Payment\Controllers\PaymentController;
use App\Modules\Payment\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Webhook回调（无需认证，由签名验证保护）
Route::prefix('webhooks')->group(function () {
    Route::post('/stripe', [WebhookController::class, 'stripe']);
    Route::post('/alipay', [WebhookController::class, 'alipay']);
    Route::post('/wechat', [WebhookController::class, 'wechat']);
});

Route::middleware('auth:api')->group(function () {
    Route::apiResource('payments', PaymentController::class);
    Route::post('/payments/{id}/callback', [PaymentController::class, 'callback']);
    Route::post('/payments/{id}/refund', [PaymentController::class, 'refund']);
    Route::get('/payments/{id}/sync', [PaymentController::class, 'syncStatus']);
});
