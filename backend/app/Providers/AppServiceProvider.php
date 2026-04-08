<?php

namespace App\Providers;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Subscription;
use App\Modules\Billing\Observers\BillObserver;
use App\Modules\Billing\Policies\BillPolicy;
use App\Modules\Payment\Observers\PaymentObserver;
use App\Modules\Payment\Policies\PaymentPolicy;
use App\Modules\Payment\Policies\RefundPolicy;
use App\Modules\Subscription\Observers\SubscriptionObserver;
use App\Modules\Subscription\Policies\SubscriptionPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * 应用服务提供者
 * 
 * 注册全局服务、Policies、Observers 等。
 * 模块级服务绑定已迁移到 ModuleServiceProvider 自动加载。
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 模块服务绑定已由 ModuleServiceProvider 自动加载
        // 此处仅注册非模块化的共享服务
    }

    public function boot(): void
    {
        // 注册 Policies（使用 App\Models 别名类，与 Gate 解析一致）
        Gate::policy(Subscription::class, SubscriptionPolicy::class);
        Gate::policy(Bill::class, BillPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Refund::class, RefundPolicy::class);

        // 注册 Observers
        Subscription::observe(SubscriptionObserver::class);
        Bill::observe(BillObserver::class);
        Payment::observe(PaymentObserver::class);

        Model::preventLazyLoading(!$this->app->isProduction());

        if ($this->app->environment('local', 'development')) {
            DB::listen(function ($query) {
                if ($query->time > 100) {
                    Log::channel('slow-query')->warning('慢查询检测', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time' => $query->time . 'ms',
                    ]);
                }
            });
        }

        Model::shouldBeStrict(!$this->app->isProduction());
    }
}
