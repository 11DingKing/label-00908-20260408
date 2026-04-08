<?php

/**
 * Billing 模块配置
 * 
 * 定义模块的服务绑定，由 ModuleServiceProvider 自动加载
 */
return [
    // 接口 => 实现 的绑定
    'bindings' => [
        \App\Modules\Billing\Contracts\BillingServiceInterface::class 
            => \App\Modules\Billing\Services\BillingService::class,
    ],
    
    // 单例服务
    'singletons' => [
        \App\Modules\Billing\Services\BillingService::class,
        \App\Modules\Billing\Services\TaxService::class,
        \App\Modules\Billing\Services\CouponService::class,
        \App\Modules\Billing\Services\CurrencyService::class,
        \App\Modules\Billing\Services\ReportService::class,
    ],
];
