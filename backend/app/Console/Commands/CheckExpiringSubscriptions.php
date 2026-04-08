<?php

namespace App\Console\Commands;

use App\Modules\Subscription\Jobs\SendExpirationReminder;
use App\Models\Subscription;
use Illuminate\Console\Command;

class CheckExpiringSubscriptions extends Command
{
    protected $signature = 'subscriptions:check-expiring 
                            {--days=7 : 提前多少天提醒}
                            {--queue : 使用队列发送提醒}';

    protected $description = '检查即将到期的订阅并发送提醒';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $useQueue = $this->option('queue');

        $this->info("🔍 检查 {$days} 天内到期的订阅...");

        $expiringSubscriptions = Subscription::where('status', 'active')
            ->where('auto_renew', false)
            ->whereBetween('end_date', [now(), now()->addDays($days)])
            ->with('user')
            ->get();

        if ($expiringSubscriptions->isEmpty()) {
            $this->info('✅ 没有即将到期的订阅');
            return Command::SUCCESS;
        }

        $this->info("📋 找到 {$expiringSubscriptions->count()} 个即将到期的订阅");

        $bar = $this->output->createProgressBar($expiringSubscriptions->count());
        $bar->start();

        foreach ($expiringSubscriptions as $subscription) {
            if ($useQueue) {
                SendExpirationReminder::dispatch($subscription);
            } else {
                SendExpirationReminder::dispatchSync($subscription);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('✅ 到期提醒发送完成');

        return Command::SUCCESS;
    }
}
