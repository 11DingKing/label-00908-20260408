<?php

namespace App\Modules\Billing\Jobs;

use App\Models\Subscription;
use App\Modules\Billing\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CalculateBillJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public Carbon $periodStart,
        public Carbon $periodEnd,
        public ?string $couponCode = null,
        public ?string $currency = null
    ) {}

    public function handle(BillingService $billingService): void
    {
        try {
            $bill = $billingService->calculateBill(
                $this->subscription, $this->periodStart, $this->periodEnd,
                $this->couponCode, $this->currency
            );
            Log::channel('billing')->info('计费计算任务完成', [
                'bill_id' => $bill->id, 'subscription_id' => $this->subscription->id,
                'total_amount' => $bill->total_amount, 'currency' => $bill->currency,
            ]);
        } catch (\Exception $e) {
            Log::channel('billing')->error('计费计算任务失败', [
                'subscription_id' => $this->subscription->id, 'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
