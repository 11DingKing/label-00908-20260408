# 计费系统后端

功能完善的复杂计费系统，基于 Laravel 12 构建，支持多种计费模式、多支付网关、完整的权限控制。

## 核心特性

- **模块化架构**: 按 DDD 思想拆分为 Billing、Subscription、Payment 三大模块
- **精确计费**: 使用 BCMath 高精度计算，避免浮点数精度问题
- **阶梯计费**: 支持 Volume（总量阶梯）和 Graduated（累进阶梯）两种模式
- **差异化定价**: 不同订阅等级可配置不同的使用量单价
- **多支付网关**: 集成 Stripe（官方 SDK）、支付宝、微信支付
- **RBAC 权限**: 基于角色的访问控制，支持细粒度权限管理
- **高并发优化**: Redis 缓冲写入、读写分离支持

## 技术栈

| 组件 | 版本 | 说明 |
|------|------|------|
| Laravel | 12.x | PHP 框架 |
| PHP | 8.4+ | 运行环境 |
| MySQL | 8.0 | 主数据库 |
| Redis | 7.x | 缓存/队列/使用量缓冲 |
| JWT | tymon/jwt-auth 2.x | API 认证 |
| Stripe SDK | 16.x | 支付集成 |
| DomPDF | 3.x | PDF 账单生成 |

## 项目结构

```
app/
├── Constants/                    # 常量定义
│   └── RoleConstants.php         # 角色常量
├── Http/
│   ├── Controllers/Api/          # 全局 API 控制器
│   ├── Middleware/               # 中间件（认证、权限、日志）
│   └── Requests/                 # 请求验证（别名，指向模块）
├── Models/                       # 模型别名（Factory 兼容层）
├── Modules/                      # 核心业务模块
│   ├── Billing/                  # 计费模块
│   │   ├── Controllers/          # BillController, ReportController
│   │   ├── Services/             # BillingService, TaxService, CouponService
│   │   ├── Models/               # Bill, BillItem, Coupon, TaxRate
│   │   ├── Policies/             # BillPolicy
│   │   ├── Events/               # BillCreated
│   │   ├── Jobs/                 # CalculateBillJob
│   │   └── Exceptions/           # BillingException 及子类
│   ├── Subscription/             # 订阅模块
│   │   ├── Controllers/          # SubscriptionController, UsageController
│   │   ├── Services/             # UsageService（支持 Redis 缓冲）
│   │   ├── Models/               # Subscription, SubscriptionPlan, UsageRecord
│   │   ├── Requests/             # CreateSubscriptionRequest 等
│   │   └── Policies/             # SubscriptionPolicy
│   └── Payment/                  # 支付模块
│       ├── Controllers/          # PaymentController, WebhookController
│       ├── Services/             # PaymentService, PaymentGatewayManager
│       ├── Gateways/             # StripeGateway, AlipayGateway, WechatPayGateway
│       ├── Models/               # Payment, Refund
│       ├── Requests/             # CreatePaymentRequest
│       └── Exceptions/           # PaymentGatewayException 等
├── Providers/
│   ├── AppServiceProvider.php    # Policies, Observers 注册
│   └── ModuleServiceProvider.php # 模块自动加载
└── Services/
    └── ReportService.php         # 报表服务
```

## 快速开始

### Docker 部署

```bash
# 启动服务
docker compose up -d

# 运行迁移
docker compose exec backend php artisan migrate

# 运行测试
docker compose run --rm backend php vendor/bin/phpunit
```

### 本地开发

```bash
# 安装依赖
composer install

# 配置环境
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# 运行迁移
php artisan migrate

# 启动服务
php artisan serve
```

## 计费引擎

### 计费模式

1. **固定订阅费**: 按计费周期（月/季/年）收取固定费用
2. **使用量计费**: 按实际使用量计费，支持：
   - 简单计费: `数量 × 单价`
   - 差异化定价: 不同订阅等级不同单价
   - 阶梯计费: Volume 或 Graduated 模式

### 阶梯计费配置示例

```json
{
  "tiered_pricing": {
    "api_calls": {
      "type": "graduated",
      "tiers": [
        {"up_to": 10000, "unit_price": "0.10"},
        {"up_to": 100000, "unit_price": "0.08"},
        {"up_to": null, "unit_price": "0.05"}
      ]
    }
  }
}
```

**计算示例**（15000 次 API 调用，Graduated 模式）：
- 前 10000 次: 10000 × 0.10 = ¥1000
- 后 5000 次: 5000 × 0.08 = ¥400
- 总计: ¥1400

### 精确计算

所有货币计算使用 `MoneyCalculator`（BCMath）：

```php
use App\Modules\Billing\Services\MoneyCalculator;

$total = MoneyCalculator::add('0.1', '0.2');  // "0.30" (避免浮点精度问题)
$fee = MoneyCalculator::mul('1523', '0.0085'); // 精确乘法
```

## API 接口

### 认证 `/api/auth`

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | /login | 登录 |
| POST | /register | 注册 |
| GET | /me | 当前用户 |
| POST | /logout | 登出 |
| POST | /refresh | 刷新 Token |

### 订阅 `/api/subscriptions`

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /plans | 计划列表 |
| POST | / | 创建订阅 |
| PUT | /{id}/upgrade | 升级订阅 |
| PUT | /{id}/downgrade | 降级订阅 |
| POST | /{id}/cancel | 取消订阅 |

### 使用量 `/api/usage`

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | /record | 记录使用量 |
| GET | /dimensions | 计量维度 |
| GET | /statistics | 使用统计 |

### 账单 `/api/bills`

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | / | 账单列表 |
| GET | /{id} | 账单详情 |
| POST | /{id}/download | 下载 PDF |

### 支付 `/api/payments`

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | / | 创建支付 |
| GET | /{id}/sync | 同步状态 |
| POST | /{id}/refund | 申请退款 |

### Webhook `/api/webhooks`

| 方法 | 路径 | 说明 |
|------|------|------|
| POST | /stripe | Stripe 回调 |
| POST | /alipay | 支付宝回调 |
| POST | /wechat | 微信支付回调 |

### 报表 `/api/reports`

| 方法 | 路径 | 说明 |
|------|------|------|
| GET | /overview | 财务概览 |
| GET | /usage | 使用量报表 |
| GET | /revenue | 收入报表 |
| GET | /trend | 趋势分析 |
| GET | /export | 导出报表 |

## 测试

```bash
# 全部测试 (238 个用例)
php vendor/bin/phpunit

# 单元测试
php vendor/bin/phpunit tests/Unit

# 功能测试
php vendor/bin/phpunit tests/Feature

# 指定测试
php vendor/bin/phpunit tests/Unit/MoneyCalculatorTest.php
php vendor/bin/phpunit tests/Unit/TieredPricingCalculatorTest.php
```

### 测试覆盖

- **单元测试**: MoneyCalculator, TieredPricingCalculator, Models, Services
- **功能测试**: Auth, Subscription, Usage, Billing, Payment, Reports, Admin

## 定时任务

```bash
# 检查即将到期订阅
php artisan subscriptions:check-expiring

# 检查逾期账单
php artisan bills:check-overdue

# 自动续订
php artisan subscriptions:renew

# 生成账单
php artisan bills:generate

# 刷新使用量缓冲（Redis → DB）
php artisan usage:flush-buffer
```

## 配置

### 环境变量

```env
# 数据库
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=billing_system

# Redis
REDIS_HOST=127.0.0.1

# JWT
JWT_SECRET=your-secret-key
JWT_TTL=60

# 支付
STRIPE_SECRET_KEY=sk_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
ALIPAY_APP_ID=xxx
WECHAT_MCH_ID=xxx

# 计费
PAYMENT_DEFAULT_CURRENCY=CNY
PAYMENT_BASE_CURRENCY=CNY
DEFAULT_TAX_RATE=0.13

# 使用量缓冲
USAGE_BUFFER_ENABLED=true
USAGE_BUFFER_THRESHOLD=100
```

### 数据库读写分离

参见 [docs/DATABASE_SETUP.md](docs/DATABASE_SETUP.md)

## 扩展开发

### 添加新模块

1. 创建模块目录 `app/Modules/NewModule/`
2. 添加 `config.php` 定义服务绑定
3. 在 `ModuleServiceProvider::$modules` 中注册

### 添加支付网关

1. 实现 `PaymentGatewayInterface`
2. 在 `PaymentGatewayManager` 中注册
3. 添加 Webhook 处理

## 文档

- [数据库配置指南](docs/DATABASE_SETUP.md)
- [API 文档](docs/openapi.yaml)
- [模型别名说明](app/Models/README.md)
- [依赖兼容性](DEPENDENCY_COMPATIBILITY.md)

## License

MIT
