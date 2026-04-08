<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Modules\Billing\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RenewSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:renew {--dry-run : 模拟运行，不实际执行}';
    protected $description = '自动续费即将到期的订阅';

    public function __construct(private BillingService $billingService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('🔄 检查需要续费的订阅...');

        // 查找即将到期且开启自动续费的订阅
        $subscriptions = Subscription::where('status', 'active')
            ->where('auto_renew', true)
            ->where('end_date', '<=', now()->addDays(3))
            ->where('end_date', '>=', now())
            ->with(['user', 'plan'])
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('✅ 没有需要续费的订阅');
            return Command::SUCCESS;
        }

        $this->info("📋 找到 {$subscriptions->count()} 个需要续费的订阅");

        $successCount = 0;
        $failCount = 0;

        foreach ($subscriptions as $subscription) {
            $this->line("处理订阅 #{$subscription->id} (用户: {$subscription->user->name})");

            if ($dryRun) {
                $this->info("  [模拟] 将续费至 " . $subscription->end_date->addMonth()->format('Y-m-d'));
                $successCount++;
                continue;
            }

            try {
                DB::transaction(function () use ($subscription) {
                    $plan = $subscription->plan;
                    $newEndDate = $this->calculateNewEndDate($subscription->end_date, $plan->billing_cycle);

                    $subscription->update([
                        'end_date' => $newEndDate,
                    ]);

                    // 生成新的账单
                    $this->billingService->calculateBill(
                        $subscription,
                        $subscription->end_date,
                        $newEndDate
                    );
                });

                $this->info("  ✅ 续费成功");
                $successCount++;

                Log::channel('billing')->info('订阅自动续费成功', [
                    'subscription_id' => $subscription->id,
                    'user_id' => $subscription->user_id,
                ]);
            } catch (\Exception $e) {
                $this->error("  ❌ 续费失败: {$e->getMessage()}");
                $failCount++;

                Log::channel('billing')->error('订阅自动续费失败', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("✅ 处理完成: 成功 {$successCount}, 失败 {$failCount}");

        return $failCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function calculateNewEndDate(Carbon $currentEndDate, string $billingCycle): Carbon
    {
        return match ($billingCycle) {
            'monthly' => $currentEndDate->copy()->addMonth(),
            'quarterly' => $currentEndDate->copy()->addMonths(3),
            'yearly' => $currentEndDate->copy()->addYear(),
            default => $currentEndDate->copy()->addMonth(),
        };
    }
}
