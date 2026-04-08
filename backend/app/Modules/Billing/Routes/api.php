<?php

/**
 * Billing Module Routes
 * 计费模块路由 - 计费引擎和账单管理
 */

use App\Modules\Billing\Controllers\BillingController;
use App\Modules\Billing\Controllers\BillController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    // 计费引擎
    Route::post('/billing/calculate', [BillingController::class, 'calculate']);
    Route::get('/billing/rules', [BillingController::class, 'rules']);
    Route::post('/billing/proration', [BillingController::class, 'proration']);

    // 账单管理
    Route::get('/bills', [BillController::class, 'index']);
    Route::get('/bills/{id}', [BillController::class, 'show']);
    Route::get('/bills/{id}/items', [BillController::class, 'getItems']);
    Route::post('/bills/{id}/download', [BillController::class, 'download']);
});
