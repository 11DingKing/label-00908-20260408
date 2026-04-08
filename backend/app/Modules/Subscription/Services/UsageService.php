<?php

namespace App\Modules\Subscription\Services;

use App\Modules\Subscription\Contracts\UsageServiceInterface;
use App\Models\MeteringDimension;
use App\Models\UsageRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class UsageService implements UsageServiceInterface
{
    /**
     * 记录使用量（支持高并发模式）
     * 
     * @param bool $useBuffer 是否使用 Redis 缓冲（高并发场景建议开启）
     */
    public function recordUsage(User $user, string $dimensionCode, float $quantity, array $metadata = [], bool $useBuffer = false): UsageRecord
    {
        $dimension = MeteringDimension::where('code', $dimensionCode)
            ->where('is_active', true)->firstOrFail();
        $subscription = $user->activeSubscription;
        $now = now();
        $billingPeriodStart = $this->getBillingPeriodStart($user, $now);
        $billingPeriodEnd = $this->getBillingPeriodEnd($billingPeriodStart);

        // 高并发模式：先写入 Redis 缓冲，由定时任务批量写入数据库
        if ($useBuffer && config('usage.buffer_enabled', false)) {
            return $this->bufferUsageToRedis($user, $dimension, $subscription, $quantity, $metadata, $now, $billingPeriodStart, $billingPeriodEnd);
        }

        // 标准模式：直接写入数据库
        $usageRecord = UsageRecord::create([
            'user_id' => $user->id, 'subscription_id' => $subscription?->id,
            'dimension_id' => $dimension->id, 'quantity' => $quantity,
            'recorded_at' => $now, 'billing_period_start' => $billingPeriodStart,
            'billing_period_end' => $billingPeriodEnd, 'metadata' => $metadata,
        ]);

        Log::info('使用量记录已创建', [
            'user_id' => $user->id, 'dimension_code' => $dimensionCode, 'quantity' => $quantity,
        ]);
        return $usageRecord;
    }

    /**
     * 高并发缓冲：将使用量写入 Redis，稍后批量持久化
     */
    protected function bufferUsageToRedis(User $user, MeteringDimension $dimension, $subscription, float $quantity, array $metadata, Carbon $now, Carbon $billingPeriodStart, Carbon $billingPeriodEnd): UsageRecord
    {
        $bufferKey = "usage_buffer:{$user->id}:{$dimension->id}:" . $now->format('Y-m-d-H');
        $recordData = [
            'user_id' => $user->id,
            'subscription_id' => $subscription?->id,
            'dimension_id' => $dimension->id,
            'quantity' => $quantity,
            'recorded_at' => $now->toDateTimeString(),
            'billing_period_start' => $billingPeriodStart->toDateString(),
            'billing_period_end' => $billingPeriodEnd->toDateString(),
            'metadata' => $metadata,
        ];

        // 使用 Redis List 存储，支持批量消费
        Redis::rpush($bufferKey, json_encode($recordData));
        Redis::expire($bufferKey, 7200); // 2小时过期

        // 同时更新聚合计数器（用于实时查询）
        $aggregateKey = "usage_aggregate:{$user->id}:{$dimension->id}:" . $billingPeriodStart->format('Y-m-d');
        Redis::incrbyfloat($aggregateKey, $quantity);
        Redis::expire($aggregateKey, 86400 * 35); // 35天过期

        Log::info('使用量已缓冲至Redis', [
            'user_id' => $user->id, 'dimension_code' => $dimension->code, 'quantity' => $quantity,
        ]);

        // 返回一个未持久化的 UsageRecord 实例（用于 API 响应）
        return new UsageRecord($recordData + ['id' => null]);
    }

    /**
     * 批量将 Redis 缓冲数据写入数据库（由定时任务调用）
     */
    public function flushBufferedUsage(int $batchSize = 1000): int
    {
        $pattern = 'usage_buffer:*';
        $keys = Redis::keys($pattern);
        $totalFlushed = 0;

        foreach ($keys as $key) {
            $records = [];
            while (($data = Redis::lpop($key)) !== null && count($records) < $batchSize) {
                $record = json_decode($data, true);
                $record['created_at'] = now();
                $record['updated_at'] = now();
                $records[] = $record;
            }

            if (!empty($records)) {
                UsageRecord::insert($records);
                $totalFlushed += count($records);
                Log::info('批量写入使用量记录', ['count' => count($records), 'key' => $key]);
            }
        }

        return $totalFlushed;
    }

    /**
     * 获取实时使用量（优先从 Redis 聚合读取）
     */
    public function getRealTimeUsage(User $user, string $dimensionCode, ?Carbon $periodStart = null): float
    {
        $dimension = MeteringDimension::where('code', $dimensionCode)->first();
        if (!$dimension) return 0;

        $periodStart = $periodStart ?? $this->getBillingPeriodStart($user, now());
        $aggregateKey = "usage_aggregate:{$user->id}:{$dimension->id}:" . $periodStart->format('Y-m-d');

        // 先查 Redis 聚合
        $redisTotal = (float) Redis::get($aggregateKey) ?: 0;

        // 再查数据库（已持久化的数据）
        $dbTotal = (float) UsageRecord::where('user_id', $user->id)
            ->where('dimension_id', $dimension->id)
            ->where('billing_period_start', $periodStart->toDateString())
            ->sum('quantity');

        return $redisTotal + $dbTotal;
    }

    public function getUsageStatistics(User $user, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = UsageRecord::where('user_id', $user->id)->with('dimension');
        if ($startDate) $query->where('recorded_at', '>=', $startDate);
        if ($endDate) $query->where('recorded_at', '<=', $endDate);
        $records = $query->get();

        $statistics = [];
        foreach ($records->groupBy('dimension_id') as $dimensionId => $groupedRecords) {
            $dimension = $groupedRecords->first()->dimension;
            $statistics[] = [
                'dimension_code' => $dimension->code, 'dimension_name' => $dimension->name,
                'unit' => $dimension->unit, 'total_quantity' => (float) $groupedRecords->sum('quantity'),
                'record_count' => $groupedRecords->count(),
            ];
        }
        return $statistics;
    }

    protected function getBillingPeriodStart(User $user, Carbon $date): Carbon
    {
        $subscription = $user->activeSubscription;
        if (!$subscription) return $date->copy()->startOfMonth();
        $startDate = Carbon::parse($subscription->start_date);
        $dayOfMonth = $startDate->day;
        if ($date->day >= $dayOfMonth) {
            return $date->copy()->day($dayOfMonth)->startOfDay();
        }
        return $date->copy()->subMonth()->day($dayOfMonth)->startOfDay();
    }

    protected function getBillingPeriodEnd(Carbon $periodStart): Carbon
    {
        return $periodStart->copy()->addMonth()->subDay()->endOfDay();
    }
}
