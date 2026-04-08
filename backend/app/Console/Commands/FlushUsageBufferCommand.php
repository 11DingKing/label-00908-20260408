<?php

namespace App\Console\Commands;

use App\Modules\Subscription\Services\UsageService;
use Illuminate\Console\Command;

class FlushUsageBufferCommand extends Command
{
    protected $signature = 'usage:flush-buffer 
                            {--batch-size=1000 : 每批次处理的记录数}';

    protected $description = '将 Redis 中缓冲的使用量记录批量写入数据库';

    public function __construct(private UsageService $usageService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!config('usage.buffer_enabled', false)) {
            $this->info('使用量缓冲模式未启用，跳过执行');
            return Command::SUCCESS;
        }

        $batchSize = (int) $this->option('batch-size');
        $this->info("🔄 开始刷新使用量缓冲区（批次大小: {$batchSize}）...");

        $flushed = $this->usageService->flushBufferedUsage($batchSize);

        if ($flushed > 0) {
            $this->info("✅ 成功写入 {$flushed} 条使用量记录");
        } else {
            $this->info('📭 缓冲区为空，无需处理');
        }

        return Command::SUCCESS;
    }
}
