<?php

namespace App\Console\Commands;

use App\Modules\Billing\Jobs\CalculateBillJob;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateBillsCommand extends Command
{
    protected $signature = 'billing:generate 
                            {--user= : 指定用户ID}
                            {--period= : 计费周期 (格式: YYYY-MM)}
                            {--sync : 同步执行（仅限调试，生产环境默认使用队列）}';

    protected $description = '为活跃订阅生成账单（默认通过队列异步处理）';

    public function handle(): int
    {
        $userId = $this->option('user');
        $period = $this->option('period');
        $forceSync = $this->option('sync');

        // 生产环境禁止同步执行
        if ($forceSync && app()->isProduction()) {
            $this->error('⛔ 生产环境不允许同步执行，请移除 --sync 参数');
            return Command::FAILURE;
        }

        // 非生产环境使用 --sync 时给出提示
        if ($forceSync) {
            $this->warn('⚠️  同步模式仅限调试使用，生产环境将自动使用队列');
        }

        // 确定计费周期
        if ($period) {
            $periodStart = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();
        } else {
            $periodStart = now()->subMonth()->startOfMonth();
            $periodEnd = $periodStart->copy()->endOfMonth();
        }

        $this->info("📅 计费周期: {$periodStart->format('Y-m-d')} 至 {$periodEnd->format('Y-m-d')}");

        // 获取需要生成账单的订阅
        $query = Subscription::where('status', 'active')
            ->where('start_date', '<=', $periodEnd)
            ->where(function ($q) use ($periodStart) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $periodStart);
            });

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $subscriptions = $query->with(['user', 'plan'])->get();

        if ($subscriptions->isEmpty()) {
            $this->warn('⚠️  没有找到需要生成账单的订阅');
            return Command::SUCCESS;
        }

        $this->info("📋 找到 {$subscriptions->count()} 个订阅需要处理");

        $bar = $this->output->createProgressBar($subscriptions->count());
        $bar->start();

        foreach ($subscriptions as $subscription) {
            if ($forceSync) {
                // 仅调试模式：同步执行
                CalculateBillJob::dispatchSync($subscription, $periodStart, $periodEnd);
            } else {
                // 默认：队列异步处理
                CalculateBillJob::dispatch($subscription, $periodStart, $periodEnd);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $message = $forceSync ? '账单生成完成（同步模式）' : '账单生成任务已全部加入队列';
        $this->info("✅ {$message}");

        return Command::SUCCESS;
    }
}
