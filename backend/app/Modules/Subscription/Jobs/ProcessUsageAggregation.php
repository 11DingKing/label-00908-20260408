<?php

namespace App\Modules\Subscription\Jobs;

use App\Models\UsageRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessUsageAggregation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public User $user,
        public Carbon $periodStart,
        public Carbon $periodEnd
    ) {}

    public function handle(): void
    {
        try {
            $aggregatedUsage = UsageRecord::where('user_id', $this->user->id)
                ->whereBetween('recorded_at', [$this->periodStart, $this->periodEnd])
                ->select('dimension_id', DB::raw('SUM(quantity) as total_quantity'))
                ->groupBy('dimension_id')
                ->get();
            Log::channel('billing')->info('使用量聚合完成', [
                'user_id' => $this->user->id,
                'period' => $this->periodStart->format('Y-m-d') . ' ~ ' . $this->periodEnd->format('Y-m-d'),
                'dimensions_count' => $aggregatedUsage->count(),
            ]);
        } catch (\Exception $e) {
            Log::channel('billing')->error('使用量聚合失败', [
                'user_id' => $this->user->id, 'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
