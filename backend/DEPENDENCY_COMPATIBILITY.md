# 依赖版本兼容性说明

## Laravel 12 兼容性矩阵

| 依赖包 | 当前版本 | Laravel 12 兼容性 | 备注 |
|--------|----------|-------------------|------|
| `tymon/jwt-auth` | ^2.1 | ⚠️ 需验证 | 该包更新较慢，建议关注 [GitHub Issues](https://github.com/tymondesigns/jwt-auth)，如不兼容可考虑迁移到 `php-open-source-saver/jwt-auth` |
| `barryvdh/laravel-dompdf` | ^3.0 | ✅ 兼容 | v3.x 支持 Laravel 10-12 |
| `predis/predis` | ^2.2 | ✅ 兼容 | 纯 PHP 实现，无框架耦合 |
| `guzzlehttp/guzzle` | ^7.8 | ✅ 兼容 | Laravel 12 内置依赖 |
| `stripe/stripe-php` | ^16.0 | ✅ 兼容 | Stripe 官方 SDK |

## 风险缓解措施

### tymon/jwt-auth 替代方案
如遇兼容性问题，可迁移到社区维护的 fork：
```bash
composer remove tymon/jwt-auth
composer require php-open-source-saver/jwt-auth
```
命名空间从 `Tymon\JWTAuth` 改为 `PHPOpenSourceSaver\JWTAuth`，API 完全兼容。

### 建议
1. 在 CI/CD 中添加 `composer audit` 检查安全漏洞
2. 使用 `composer outdated` 定期检查依赖更新
3. 锁定 `composer.lock` 确保部署一致性
