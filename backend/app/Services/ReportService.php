<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\MeteringDimension;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\UsageRecord;
use App\Models\User;
use App\Modules\Billing\Services\MoneyCalculator;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * 获取财务概览
     */
    public function getFinancialOverview(?int $userId = null, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now()->endOfMonth();

        $billQuery = Bill::whereBetween('created_at', [$startDate, $endDate]);
        $paymentQuery = Payment::where('status', 'completed')
            ->whereBetween('paid_at', [$startDate, $endDate]);

        if ($userId) {
            $billQuery->where('user_id', $userId);
            $paymentQuery->where('user_id', $userId);
        }

        // 使用 BCMath 精确计算金额汇总
        $bills = $billQuery->get();
        $paidBills = $bills->where('status', 'paid');
        $pendingBills = $bills->where('status', 'pending');
        $overdueBills = $bills->where('status', 'overdue');

        $totalBillAmount = $this->sumAmounts($bills, 'total_amount');
        $paidAmount = $this->sumAmounts($paidBills, 'total_amount');
        $pendingAmount = $this->sumAmounts($pendingBills, 'total_amount');
        $overdueAmount = $this->sumAmounts($overdueBills, 'total_amount');
        $subscriptionRevenue = $this->sumAmounts($bills, 'subscription_fee');
        $usageRevenue = $this->sumAmounts($bills, 'usage_fee');

        $payments = $paymentQuery->get();
        $totalPaymentAmount = $this->sumAmounts($payments, 'amount');

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'bills' => [
                'total_count' => $bills->count(),
                'total_amount' => MoneyCalculator::toFloat($totalBillAmount),
                'paid_count' => $paidBills->count(),
                'paid_amount' => MoneyCalculator::toFloat($paidAmount),
                'pending_count' => $pendingBills->count(),
                'pending_amount' => MoneyCalculator::toFloat($pendingAmount),
                'overdue_count' => $overdueBills->count(),
                'overdue_amount' => MoneyCalculator::toFloat($overdueAmount),
            ],
            'payments' => [
                'total_count' => $payments->count(),
                'total_amount' => MoneyCalculator::toFloat($totalPaymentAmount),
            ],
            'subscription_revenue' => MoneyCalculator::toFloat($subscriptionRevenue),
            'usage_revenue' => MoneyCalculator::toFloat($usageRevenue),
        ];
    }

    /**
     * 获取使用量报表
     * 
     * 支持订阅等级差异化定价：优先使用用户订阅计划的自定义单价
     */
    public function getUsageReport(int $userId, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->startOfMonth();
        $endDate = $endDate ?? now()->endOfMonth();

        // 获取用户当前订阅计划的自定义单价
        $user = User::find($userId);
        $customPricing = [];
        $includedUsage = [];
        
        if ($user) {
            $activeSubscription = $user->activeSubscription;
            if ($activeSubscription && $activeSubscription->plan) {
                $customPricing = $activeSubscription->plan->usage_pricing ?? [];
                $includedUsage = $activeSubscription->plan->included_usage ?? [];
            }
        }

        $usageData = UsageRecord::where('user_id', $userId)
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->select('dimension_id', DB::raw('SUM(quantity) as total_quantity'), DB::raw('COUNT(*) as record_count'))
            ->groupBy('dimension_id')
            ->get();

        $result = [];
        foreach ($usageData as $usage) {
            $dimension = MeteringDimension::find($usage->dimension_id);
            if (!$dimension) continue;

            $totalQuantity = (string) $usage->total_quantity;
            $included = (string) ($includedUsage[$dimension->code] ?? 0);
            
            // 计算可计费数量（扣除赠送额度）
            $billableQuantity = MoneyCalculator::max(MoneyCalculator::sub($totalQuantity, $included), '0');
            
            // 优先使用订阅计划的自定义单价，否则使用维度默认单价
            $unitPrice = (string) ($customPricing[$dimension->code] ?? $dimension->unit_price);
            
            // 使用 BCMath 精确计算预估费用
            $estimatedCost = MoneyCalculator::round(MoneyCalculator::mul($billableQuantity, $unitPrice));

            $result[] = [
                'dimension_code' => $dimension->code,
                'dimension_name' => $dimension->name,
                'unit' => $dimension->unit,
                'total_quantity' => MoneyCalculator::toFloat($totalQuantity, 4),
                'included_quantity' => MoneyCalculator::toFloat($included, 4),
                'billable_quantity' => MoneyCalculator::toFloat($billableQuantity, 4),
                'unit_price' => MoneyCalculator::toFloat($unitPrice, 4),
                'record_count' => $usage->record_count,
                'estimated_cost' => MoneyCalculator::toFloat($estimatedCost),
            ];
        }

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'usage' => $result,
        ];
    }

    /**
     * 获取收入趋势报表
     */
    public function getRevenueTrend(?Carbon $startDate = null, ?Carbon $endDate = null, string $groupBy = 'day'): array
    {
        $startDate = $startDate ?? now()->subDays(30);
        $endDate = $endDate ?? now();

        $dateFormat = match ($groupBy) {
            'month' => '%Y-%m',
            'week' => '%Y-%u',
            default => '%Y-%m-%d',
        };

        $revenue = Payment::where('status', 'completed')
            ->whereBetween('paid_at', [$startDate, $endDate])
            ->select(
                DB::raw("DATE_FORMAT(paid_at, '{$dateFormat}') as period"),
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('COUNT(*) as payment_count')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // 使用 BCMath 精确处理金额
        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'group_by' => $groupBy,
            'data' => $revenue->map(fn($item) => [
                'period' => $item->period,
                'total_amount' => MoneyCalculator::toFloat((string) $item->total_amount),
                'payment_count' => $item->payment_count,
            ])->toArray(),
        ];
    }

    /**
     * 获取订阅统计
     */
    public function getSubscriptionStats(): array
    {
        $stats = Subscription::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $planStats = Subscription::where('status', 'active')
            ->join('subscription_plans', 'subscriptions.plan_id', '=', 'subscription_plans.id')
            ->select('subscription_plans.name', DB::raw('COUNT(*) as count'))
            ->groupBy('subscription_plans.name')
            ->pluck('count', 'name')
            ->toArray();

        return [
            'by_status' => [
                'active' => $stats['active'] ?? 0,
                'cancelled' => $stats['cancelled'] ?? 0,
                'expired' => $stats['expired'] ?? 0,
                'pending' => $stats['pending'] ?? 0,
            ],
            'by_plan' => $planStats,
            'total' => array_sum($stats),
        ];
    }

    /**
     * 获取用户统计
     */
    public function getUserStats(): array
    {
        $totalUsers = User::count();
        $activeUsers = User::where('status', 'active')->count();
        $subscribedUsers = User::whereHas('activeSubscription')->count();

        $newUsersThisMonth = User::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // 使用 BCMath 计算百分比
        $subscriptionRate = $totalUsers > 0 
            ? MoneyCalculator::toFloat(MoneyCalculator::mul(MoneyCalculator::div((string) $subscribedUsers, (string) $totalUsers), '100'))
            : 0;

        return [
            'total' => $totalUsers,
            'active' => $activeUsers,
            'subscribed' => $subscribedUsers,
            'new_this_month' => $newUsersThisMonth,
            'subscription_rate' => $subscriptionRate,
        ];
    }

    /**
     * 使用 BCMath 精确汇总金额
     */
    protected function sumAmounts($collection, string $field): string
    {
        $sum = '0';
        foreach ($collection as $item) {
            $sum = MoneyCalculator::add($sum, (string) ($item->{$field} ?? 0));
        }
        return $sum;
    }
}
