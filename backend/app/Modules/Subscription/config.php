<?php

/**
 * Subscription 模块配置
 * 
 * 定义模块的服务绑定，由 ModuleServiceProvider 自动加载
 */
return [
    // 接口 => 实现 的绑定
    'bindings' => [
        \App\Modules\Subscription\Contracts\UsageServiceInterface::class 
            => \App\Modules\Subscription\Services\UsageService::class,
    ],
    
    // 单例服务
    'singletons' => [
        \App\Modules\Subscription\Services\UsageService::class,
    ],
];
