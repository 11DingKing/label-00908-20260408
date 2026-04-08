<?php

namespace App\Modules\Billing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Jobs\CalculateBillJob;
use App\Models\MeteringDimension;
use App\Models\SubscriptionPlan;
use App\Models\TaxRate;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Billing\Services\CurrencyService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(
        protected BillingService $billingService,
        protected CurrencyService $currencyService
    ) {}

    public function calculate(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->activeSubscription;
        if (!$subscription) {
            return response()->json(['message' => '用户没有活跃的订阅', 'error_code' => 'NO_ACTIVE_SUBSCRIPTION'], 400);
        }
        $periodStart = $request->input('period_start') ? Carbon::parse($request->input('period_start')) : now()->startOfMonth();
        $periodEnd = $request->input('period_end') ? Carbon::parse($request->input('period_end')) : now()->endOfMonth();
        CalculateBillJob::dispatch($subscription, $periodStart, $periodEnd, $request->input('coupon_code'), $request->input('currency'));
        return response()->json(['message' => '计费计算任务已提交，请稍后查看账单']);
    }

    public function rules(Request $request): JsonResponse
    {
        $dimensions = MeteringDimension::active()->get()->map(fn($d) => [
            'code' => $d->code, 'name' => $d->name, 'unit' => $d->unit, 'unit_price' => (float) $d->unit_price,
        ]);
        $plans = SubscriptionPlan::active()->orderBy('sort_order')->orderBy('price')->get()->map(fn($p) => [
            'name' => $p->name, 'code' => $p->code, 'billing_cycle' => $p->billing_cycle,
            'price' => (float) $p->price, 'currency' => $p->currency ?? config('payment.default_currency', 'CNY'),
            'tax_rate' => (float) ($p->tax_rate ?? 0), 'included_usage' => $p->included_usage ?? [],
        ]);
        $taxRates = TaxRate::active()->get()->map(fn($t) => [
            'code' => $t->code, 'name' => $t->name, 'rate' => (float) $t->rate,
            'region' => $t->region, 'is_inclusive' => $t->is_inclusive,
        ]);
        return response()->json([
            'data' => [
                'billing_cycles' => ['monthly', 'quarterly', 'yearly'],
                'supported_currencies' => $this->currencyService->getSupportedCurrencies(),
                'exchange_rates' => config('payment.exchange_rates', []),
                'plans' => $plans, 'metering_dimensions' => $dimensions, 'tax_rates' => $taxRates,
                'rules' => [
                    'proration' => '升级/降级时按剩余天数比例计算费用差额',
                    'overdue_grace_period_days' => 7,
                    'usage_billing' => '超出订阅计划包含额度的使用量按维度单价计费',
                    'tax_calculation' => '根据地区税率自动计算税费',
                    'coupon_support' => '支持百分比和固定金额两种优惠券类型',
                    'refund_policy' => '支持全额和部分退款',
                    'multi_currency' => '支持多币种，以' . config('payment.base_currency', 'CNY') . '为基准货币',
                ],
            ],
        ]);
    }

    public function proration(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->activeSubscription;
        if (!$subscription) {
            return response()->json(['message' => '用户没有活跃的订阅', 'error_code' => 'NO_ACTIVE_SUBSCRIPTION'], 400);
        }
        $request->validate(['plan_id' => 'required|exists:subscription_plans,id']);
        $newPlan = SubscriptionPlan::findOrFail($request->plan_id);
        $proration = $this->billingService->calculateProration($subscription, $newPlan);
        return response()->json(['data' => $proration]);
    }
}
