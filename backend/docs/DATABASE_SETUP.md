# 数据库配置指南

本文档介绍如何配置数据库连接，包括读写分离和高性能优化。

## 基础配置

### 1. 环境变量配置

复制 `.env.example` 到 `.env` 并配置数据库连接：

```bash
cp .env.example .env
```

编辑 `.env` 文件：

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=billing_system
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 2. 运行迁移

```bash
php artisan migrate
```

## 读写分离配置

对于高并发场景，建议配置读写分离。

### 配置方式

在 `config/database.php` 中，MySQL 连接已预配置读写分离支持：

```php
'mysql' => [
    'read' => [
        'host' => [
            env('DB_READ_HOST_1', env('DB_HOST', '127.0.0.1')),
            env('DB_READ_HOST_2', env('DB_HOST', '127.0.0.1')),
        ],
    ],
    'write' => [
        'host' => [
            env('DB_HOST', '127.0.0.1'),
        ],
    ],
    // ... 其他配置
],
```

### 环境变量

```env
# 主库（写）
DB_HOST=master.db.example.com

# 从库（读）
DB_READ_HOST_1=slave1.db.example.com
DB_READ_HOST_2=slave2.db.example.com
```

### 强制使用主库

某些场景需要强制读取主库（如写入后立即读取）：

```php
// 方式1：使用 sticky 选项（已在配置中启用）
// 同一请求中写入后的读取会自动使用主库

// 方式2：手动指定
DB::connection()->getReadPdo(); // 从库
DB::connection()->getPdo();     // 主库
```

## 连接池配置

### PHP-FPM 模式

标准 PHP-FPM 模式下，每个请求都会创建新的数据库连接。优化方式：

1. **使用持久连接**（谨慎使用）：

```php
// config/database.php
'mysql' => [
    'options' => [
        PDO::ATTR_PERSISTENT => true,
    ],
],
```

2. **配置 MySQL 连接参数**：

```env
# 增加 MySQL 最大连接数
# 在 MySQL 配置中设置
max_connections = 500
wait_timeout = 28800
```

### Laravel Octane 模式（推荐高并发场景）

Laravel Octane 使用 Swoole/RoadRunner 提供真正的连接池：

```bash
# 安装 Octane
composer require laravel/octane

# 安装 Swoole
pecl install swoole

# 配置 Octane
php artisan octane:install
```

配置 `config/octane.php`：

```php
'warm' => [
    // 预热数据库连接
    ...Octane::defaultServicesToWarm(),
],
```

启动 Octane：

```bash
php artisan octane:start --workers=4 --task-workers=6
```

## Docker 环境配置

### docker-compose.yml 示例

```yaml
services:
  mysql-master:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: secret
      MYSQL_DATABASE: billing_system
    volumes:
      - mysql_master_data:/var/lib/mysql
    command: --server-id=1 --log-bin=mysql-bin --binlog-format=ROW

  mysql-slave:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: secret
    volumes:
      - mysql_slave_data:/var/lib/mysql
    command: --server-id=2 --relay-log=relay-bin --read-only=1
    depends_on:
      - mysql-master

  backend:
    build: ./backend
    environment:
      DB_HOST: mysql-master
      DB_READ_HOST_1: mysql-slave
```

## 性能优化建议

### 1. 索引优化

项目已在迁移中添加了关键索引，确保运行：

```bash
php artisan migrate
```

### 2. 查询优化

- 使用 Eager Loading 避免 N+1 问题
- 项目已启用 `Model::preventLazyLoading()` 检测

### 3. 缓存配置

```env
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### 4. 慢查询监控

项目已配置慢查询日志（>100ms），查看：

```bash
tail -f storage/logs/slow-query.log
```

## 故障排查

### 连接超时

```env
# 增加连接超时时间
DB_TIMEOUT=30
```

### 连接数过多

检查并调整 MySQL 配置：

```sql
SHOW VARIABLES LIKE 'max_connections';
SHOW STATUS LIKE 'Threads_connected';
```

### 读写分离不生效

确认 `sticky` 选项已启用，并检查从库同步状态：

```sql
-- 在从库执行
SHOW SLAVE STATUS\G
```
