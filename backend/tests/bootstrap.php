<?php

require __DIR__.'/../vendor/autoload.php';

// 确保测试环境变量被正确加载
$_ENV['APP_ENV'] = 'testing';
$_ENV['DB_CONNECTION'] = 'sqlite';
$_ENV['DB_DATABASE'] = ':memory:';

// 确保视图缓存目录存在（测试环境中可能不存在）
$viewCachePath = __DIR__.'/../storage/framework/views';
if (!is_dir($viewCachePath)) {
    mkdir($viewCachePath, 0755, true);
}
