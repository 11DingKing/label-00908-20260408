<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\AdminController;
use App\Modules\Billing\Controllers\ReportController;
use App\Modules\Subscription\Controllers\SubscriptionController;
use App\Modules\Subscription\Controllers\UsageController;
use App\Modules\Billing\Controllers\BillingController;
use App\Modules\Billing\Controllers\BillController;
use App\Modules\Payment\Controllers\PaymentController;
use App\Modules\Payment\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// 健康检查路由（无需认证）
Route::prefix('health')->group(function () {
    Route::get('/', [HealthController::class, 'check']);
    Route::get('/database', [HealthController::class, 'database']);
});

// 支付网关Webhook回调（无需认证，由签名验证保护）
Route::prefix('webhooks')->group(function () {
    Route::post('/stripe', [WebhookController::class, 'stripe']);
    Route::post('/alipay', [WebhookController::class, 'alipay']);
    Route::post('/wechat', [WebhookController::class, 'wechat']);
});

// 认证路由
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::middleware('auth:api')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/password', [AuthController::class, 'changePassword']);
    });
});

// 需要认证的路由
Route::middleware('auth:api')->group(function () {
    // 订阅管理（Subscription 模块）
    Route::get('/subscriptions/plans', [SubscriptionController::class, 'getPlans']);
    Route::get('/subscriptions/plans/{id}', [SubscriptionController::class, 'getPlan']);
    Route::apiResource('subscriptions', SubscriptionController::class);
    Route::put('/subscriptions/{id}/upgrade', [SubscriptionController::class, 'upgrade']);
    Route::put('/subscriptions/{id}/downgrade', [SubscriptionController::class, 'downgrade']);
    Route::post('/subscriptions/{id}/cancel', [SubscriptionController::class, 'cancel']);

    // 使用量计量（Subscription 模块）
    Route::post('/usage/record', [UsageController::class, 'record']);
    Route::get('/usage/dimensions', [UsageController::class, 'getDimensions']);
    Route::get('/usage/records', [UsageController::class, 'getRecords']);
    Route::get('/usage/statistics', [UsageController::class, 'getStatistics']);

    // 计费引擎（Billing 模块）
    Route::post('/billing/calculate', [BillingController::class, 'calculate']);
    Route::get('/billing/rules', [BillingController::class, 'rules']);
    Route::post('/billing/proration', [BillingController::class, 'proration']);

    // 账单管理（Billing 模块）
    Route::get('/bills', [BillController::class, 'index']);
    Route::get('/bills/{id}', [BillController::class, 'show']);
    Route::get('/bills/{id}/items', [BillController::class, 'getItems']);
    Route::post('/bills/{id}/download', [BillController::class, 'download']);

    // 支付（Payment 模块）
    Route::apiResource('payments', PaymentController::class);
    Route::post('/payments/{id}/callback', [PaymentController::class, 'callback']);
    Route::post('/payments/{id}/refund', [PaymentController::class, 'refund']);
    Route::get('/payments/{id}/sync', [PaymentController::class, 'syncStatus']);

    // 财务报表
    Route::get('/reports/overview', [ReportController::class, 'overview']);
    Route::get('/reports/usage', [ReportController::class, 'usage']);
    Route::get('/reports/revenue', [ReportController::class, 'revenue']);
    Route::get('/reports/export', [ReportController::class, 'export']);
    Route::get('/reports/trend', [ReportController::class, 'trend']);
    Route::get('/reports/subscription-stats', [ReportController::class, 'subscriptionStats']);

    // 管理后台
    Route::prefix('admin')->group(function () {
        Route::get('/users', [AdminController::class, 'getUsers']);
        Route::get('/subscriptions', [AdminController::class, 'getSubscriptions']);
        Route::get('/bills', [AdminController::class, 'getBills']);
        Route::get('/plans', [AdminController::class, 'getPlans']);
        Route::post('/plans', [AdminController::class, 'createPlan']);
        Route::put('/plans/{id}', [AdminController::class, 'updatePlan']);
        Route::get('/metering-dimensions', [AdminController::class, 'getDimensions']);
        Route::post('/metering-dimensions', [AdminController::class, 'createDimension']);
        Route::get('/coupons', [AdminController::class, 'getCoupons']);
        Route::post('/coupons', [AdminController::class, 'createCoupon']);
        Route::put('/coupons/{id}', [AdminController::class, 'updateCoupon']);
        Route::get('/tax-rates', [AdminController::class, 'getTaxRates']);
        Route::post('/tax-rates', [AdminController::class, 'createTaxRate']);
        Route::put('/tax-rates/{id}', [AdminController::class, 'updateTaxRate']);
        Route::get('/refunds', [AdminController::class, 'getRefunds']);
        Route::post('/refunds/{id}/process', [AdminController::class, 'processRefund']);
        Route::get('/roles', [AdminController::class, 'getRoles']);
        Route::post('/users/{id}/roles', [AdminController::class, 'assignRoles']);
    });
});
