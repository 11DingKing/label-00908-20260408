<?php

/**
 * Payment 模块配置
 * 
 * 定义模块的服务绑定，由 ModuleServiceProvider 自动加载
 */
return [
    // 接口 => 实现 的绑定
    'bindings' => [
        \App\Modules\Payment\Contracts\PaymentServiceInterface::class 
            => \App\Modules\Payment\Services\PaymentService::class,
    ],
    
    // 单例服务
    'singletons' => [
        \App\Modules\Payment\Services\PaymentService::class,
        \App\Modules\Payment\Services\PaymentGatewayManager::class,
    ],
];
