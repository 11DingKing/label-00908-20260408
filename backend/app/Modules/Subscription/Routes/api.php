<?php

/**
 * Subscription Module Routes
 * 订阅模块路由 - 订阅管理和使用量计量
 */

use App\Modules\Subscription\Controllers\SubscriptionController;
use App\Modules\Subscription\Controllers\UsageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    // 订阅管理
    Route::get('/subscriptions/plans', [SubscriptionController::class, 'getPlans']);
    Route::get('/subscriptions/plans/{id}', [SubscriptionController::class, 'getPlan']);
    Route::apiResource('subscriptions', SubscriptionController::class);
    Route::put('/subscriptions/{id}/upgrade', [SubscriptionController::class, 'upgrade']);
    Route::put('/subscriptions/{id}/downgrade', [SubscriptionController::class, 'downgrade']);
    Route::post('/subscriptions/{id}/cancel', [SubscriptionController::class, 'cancel']);

    // 使用量计量
    Route::post('/usage/record', [UsageController::class, 'record']);
    Route::get('/usage/dimensions', [UsageController::class, 'getDimensions']);
    Route::get('/usage/records', [UsageController::class, 'getRecords']);
    Route::get('/usage/statistics', [UsageController::class, 'getStatistics']);
});
