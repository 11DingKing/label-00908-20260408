# 使用多阶段构建，支持 ARM 和 X86
FROM --platform=$BUILDPLATFORM php:8.4-fpm-alpine AS builder

# 安装编译依赖
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    oniguruma-dev \
    $PHPIZE_DEPS

# 安装 PHP 扩展
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip opcache

# 安装 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 复制所有应用文件
COPY . .

# 安装依赖
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-scripts

# 生产阶段
FROM --platform=$BUILDPLATFORM php:8.4-fpm-alpine

# 安装运行时依赖和编译依赖
RUN apk add --no-cache \
    libpng \
    libpng-dev \
    libzip \
    libzip-dev \
    oniguruma \
    oniguruma-dev \
    nginx \
    supervisor \
    $PHPIZE_DEPS

# 安装 PHP 扩展
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip opcache

# 清理编译依赖
RUN apk del $PHPIZE_DEPS libpng-dev libzip-dev oniguruma-dev

# 配置 PHP
RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory.ini && \
    echo "upload_max_filesize=64M" > /usr/local/etc/php/conf.d/upload.ini && \
    echo "post_max_size=64M" >> /usr/local/etc/php/conf.d/upload.ini

# 复制应用文件
WORKDIR /var/www/html

COPY --from=builder /var/www/html .

# 设置权限
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html/storage && \
    chmod -R 755 /var/www/html/bootstrap/cache

# 复制 Nginx 配置
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
