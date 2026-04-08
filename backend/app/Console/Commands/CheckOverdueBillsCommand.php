<?php

namespace App\Console\Commands;

use App\Models\Bill;
use Illuminate\Console\Command;

class CheckOverdueBillsCommand extends Command
{
    protected $signature = 'billing:check-overdue {--notify : 发送逾期通知}';
    protected $description = '检查并标记逾期账单';

    public function handle(): int
    {
        $this->info('🔍 检查逾期账单...');

        $overdueBills = Bill::where('status', 'pending')
            ->where('due_date', '<', now())
            ->get();

        if ($overdueBills->isEmpty()) {
            $this->info('✅ 没有逾期账单');
            return Command::SUCCESS;
        }

        $this->warn("⚠️  发现 {$overdueBills->count()} 个逾期账单");

        $bar = $this->output->createProgressBar($overdueBills->count());
        $bar->start();

        foreach ($overdueBills as $bill) {
            $bill->update(['status' => 'overdue']);

            if ($this->option('notify')) {
                // TODO: 发送逾期通知邮件
                // Mail::to($bill->user)->send(new OverdueBillNotification($bill));
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('✅ 逾期账单处理完成');

        return Command::SUCCESS;
    }
}
