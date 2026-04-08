# 模块化架构说明

本项目采用模块化架构，核心业务逻辑位于 `app/Modules/` 目录下。

## 模块结构

```
app/Modules/
├── Billing/          # 计费模块
│   ├── Contracts/    # 接口定义
│   ├── Controllers/  # 控制器
│   ├── Events/       # 事件
│   ├── Exceptions/   # 异常
│   ├── Jobs/         # 队列任务
│   ├── Listeners/    # 事件监听器
│   ├── Mail/         # 邮件
│   ├── Models/       # 模型
│   ├── Observers/    # 观察者
│   ├── Policies/     # 策略
│   ├── Routes/       # 路由（可选）
│   └── Services/     # 服务
├── Payment/          # 支付模块
│   └── ...
└── Subscription/     # 订阅模块
    └── ...
```

## 推荐用法

**直接使用模块命名空间**（推荐）：

```php
use App\Modules\Billing\Services\BillingService;
use App\Modules\Payment\Services\PaymentService;
use App\Modules\Subscription\Services\UsageService;
```

## 向后兼容别名

为保持向后兼容，`app/Models/`、`app/Services/` 等目录下保留了别名类。
这些别名类仅继承模块实现，不包含额外逻辑。

**注意**：新代码应直接使用 `App\Modules\*` 命名空间。

## 依赖注入

服务通过 `AppServiceProvider` 注册：

```php
// 接口绑定
$this->app->singleton(BillingServiceInterface::class, BillingService::class);

// 直接获取服务
$billingService = app(BillingService::class);
```

## 配置

模块相关配置位于 `config/` 目录：
- `config/payment.php` - 支付网关、货币、税率配置
