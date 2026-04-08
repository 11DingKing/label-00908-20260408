<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * 模块化架构服务提供者
 *
 * 自动加载各模块的路由和服务绑定，实现真正的模块解耦。
 * 新增模块只需在 $modules 数组中添加模块名，无需修改其他文件。
 */
class ModuleServiceProvider extends ServiceProvider
{
    /**
     * 已注册的模块列表
     * 新增模块只需在此添加模块名
     */
    protected array $modules = [
        'Billing',
        'Subscription',
        'Payment',
    ];

    public function register(): void
    {
        foreach ($this->modules as $module) {
            $this->registerModuleServices($module);
        }
    }

    public function boot(): void
    {
        foreach ($this->modules as $module) {
            $this->loadModuleRoutes($module);
        }
    }

    /**
     * 注册模块的服务绑定
     */
    protected function registerModuleServices(string $module): void
    {
        $configPath = app_path("Modules/{$module}/config.php");
        
        if (file_exists($configPath)) {
            $config = require $configPath;
            
            // 注册接口绑定
            if (isset($config['bindings']) && is_array($config['bindings'])) {
                foreach ($config['bindings'] as $interface => $implementation) {
                    $this->app->singleton($interface, $implementation);
                }
            }
            
            // 注册单例服务
            if (isset($config['singletons']) && is_array($config['singletons'])) {
                foreach ($config['singletons'] as $service) {
                    $this->app->singleton($service);
                }
            }
        }
    }

    /**
     * 加载模块路由
     */
    protected function loadModuleRoutes(string $module): void
    {
        $routePath = app_path("Modules/{$module}/Routes/api.php");
        
        if (file_exists($routePath)) {
            Route::middleware('api')
                ->prefix('api')
                ->group($routePath);
        }
    }
}
