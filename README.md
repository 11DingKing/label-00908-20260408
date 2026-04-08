# 复杂计费系统

基于 Laravel 12 框架开发的企业级计费系统，支持订阅计费和按量计费两种模式。

## 技术栈

- **后端框架**: Laravel 12 + PHP 8.4
- **数据库**: MySQL 8.0
- **缓存/队列**: Redis 7
- **认证**: JWT (tymon/jwt-auth)
- **容器化**: Docker + Docker Compose

## 快速开始

### 前置要求

- Docker 和 Docker Compose
- 确保 3306、6379、8000 端口未被占用

### 一键启动

```bash
# 克隆项目后，启动所有服务
docker-compose up --build -d

# 查看服务状态
docker-compose ps

# 查看后端日志
docker-compose logs -f backend
```

启动后系统会自动：
1. 创建 MySQL 和 Redis 容器
2. 运行数据库迁移 (Migrations)
3. 填充初始数据 (Seeders)
4. 启动 Laravel 应用

### 验证服务

```bash
# 健康检查
curl http://localhost:8000/api/health

# 使用测试账号登录
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```

### 停止服务

```bash
docker-compose down      # 停止服务
docker-compose down -v   # 停止并清理数据
```

## 测试账号

| 角色 | 邮箱 | 密码 |
|------|------|------|
| 管理员 | admin@example.com | password |
| 普通用户 | user@example.com | password |

## 项目结构

```
.
├── backend/                # Laravel 后端应用
│   ├── app/                # 应用代码
│   ├── database/           # 迁移、种子、工厂
│   ├── routes/             # API 路由
│   └── tests/              # 单元测试 + 集成测试
├── docs/                   # 项目文档
├── docker-compose.yml      # Docker 编排配置
└── README.md               # 本文件
```

## 核心功能

| 模块 | 功能描述 |
|------|----------|
| 用户认证 | 注册、登录、JWT Token 管理 |
| 订阅管理 | 多级订阅计划、订阅创建/取消 |
| 使用量计量 | 多维度计量、实时记录 |
| 计费引擎 | 订阅费 + 超额使用量费用计算 |
| 账单管理 | 自动生成、明细查询 |
| 支付集成 | 多支付方式、回调处理 |
| 报表统计 | 财务概览、使用量分析 |
| 管理后台 | 用户/计划/维度管理 |

## API 文档

详细的 API 接口文档请参考 [backend/README.md](backend/README.md)

### 主要接口

| 模块 | 端点 | 说明 |
|------|------|------|
| 认证 | POST /api/auth/login | 用户登录 |
| 订阅 | GET /api/subscriptions/plans | 获取订阅计划 |
| 订阅 | POST /api/subscriptions | 创建订阅 |
| 使用量 | POST /api/usage/record | 记录使用量 |
| 账单 | GET /api/bills | 获取账单列表 |
| 报表 | GET /api/reports/overview | 财务概览 |

## 运行测试

```bash
# 运行全部测试 (165个测试用例)
docker run --rm -v "$(pwd)/backend:/app" -w /app php:8.4-cli sh -c \
  "apt-get update > /dev/null 2>&1 && \
   apt-get install -y libsqlite3-dev > /dev/null 2>&1 && \
   docker-php-ext-install pdo_sqlite > /dev/null 2>&1 && \
   php artisan config:clear && php artisan test"

# 仅运行单元测试
docker run --rm -v "$(pwd)/backend:/app" -w /app php:8.4-cli sh -c \
  "apt-get update > /dev/null 2>&1 && \
   apt-get install -y libsqlite3-dev > /dev/null 2>&1 && \
   docker-php-ext-install pdo_sqlite > /dev/null 2>&1 && \
   php artisan config:clear && php artisan test --testsuite=Unit"
```

## 数据库管理

```bash
# 查看迁移状态
docker exec billing_backend php artisan migrate:status

# 重置数据库
docker exec billing_backend php artisan migrate:fresh --seed
```

## 服务配置

| 服务 | 端口 | 说明 |
|------|------|------|
| Backend | 8000 | Laravel API |
| MySQL | 3306 | 数据库 (root/root123456) |
| Redis | 6379 | 缓存/队列 |

## 开发说明

详细的开发文档、API 接口说明、数据库设计请参考：

- [后端开发文档](backend/README.md)
- [项目设计文档](docs/project_design.md)
- [数据库功能清单](docs/database_features_checklist.md)

## License

MIT
