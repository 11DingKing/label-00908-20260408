<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// 每天凌晨1点检查逾期账单
Schedule::command('billing:check-overdue --notify')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// 每天凌晨2点检查并处理自动续费
Schedule::command('subscriptions:renew')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// 每月1号凌晨3点生成上月账单
Schedule::command('billing:generate --queue')
    ->monthlyOn(1, '03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// 每天上午9点检查即将到期的订阅（7天内）
Schedule::command('subscriptions:check-expiring --days=7 --queue')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// 每天上午10点检查即将到期的订阅（3天内，再次提醒）
Schedule::command('subscriptions:check-expiring --days=3 --queue')
    ->dailyAt('10:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler.log'));

// 每周清理过期的队列任务
Schedule::command('queue:prune-failed --hours=168')
    ->weekly()
    ->runInBackground();

// 每天清理过期的缓存
Schedule::command('cache:prune-stale-tags')
    ->daily()
    ->runInBackground();

// 每天凌晨4点进行数据库健康检查
Schedule::command('db:health-check')
    ->dailyAt('04:00')
    ->appendOutputTo(storage_path('logs/db-health.log'));

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
