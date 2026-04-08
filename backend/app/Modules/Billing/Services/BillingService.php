<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Contracts\BillingServiceInterface;
use App\Modules\Billing\Events\BillCreated;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Coupon;
use App\Models\MeteringDimension;
use App\Models\Subscription;
use App\Models\UsageRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BillingService implements BillingServiceInterface
{
    public function __construct(
        protected TaxService $taxService,
        protected CouponService $couponService,
        protected CurrencyService $currencyService
    ) {}

    public function calculateBill(
        Subscription $subscription,
        Carbon $periodStart,
        Carbon $periodEnd,
        ?string $couponCode = null,
        ?string $currency = null
    ): Bill {
        return DB::transaction(function () use ($subscription, $periodStart, $periodEnd, $couponCode, $currency) {
            $plan = $subscription->plan;
            $user = $subscription->user;

            // 使用 BCMath 精确计算
            $subscriptionFee = $this->calculateSubscriptionFee($subscription, $periodStart, $periodEnd);
            $usageFee = $this->calculateUsageFee($subscription, $periodStart, $periodEnd);
            $subtotal = MoneyCalculator::add($subscriptionFee, $usageFee);

            $discount = '0';
            $appliedCouponCode = null;
            if ($couponCode) {
                try {
                    $couponResult = $this->couponService->applyCoupon($couponCode, MoneyCalculator::toFloat($subtotal), $user);
                    $discount = (string) $couponResult['discount_amount'];
                    $appliedCouponCode = $couponCode;
                } catch (\Exception $e) {
                    Log::warning('优惠券应用失败', ['code' => $couponCode, 'error' => $e->getMessage()]);
                }
            }

            $afterDiscount = MoneyCalculator::sub($subtotal, $discount);
            $taxResult = $this->taxService->calculateTax(MoneyCalculator::toFloat($afterDiscount));
            $taxAmount = (string) $taxResult['tax_amount'];
            $taxRate = $taxResult['tax_rate'];
            $totalAmount = MoneyCalculator::round(MoneyCalculator::add($afterDiscount, $taxAmount));

            $billCurrency = $currency ?? config('payment.default_currency', 'CNY');
            $baseCurrency = config('payment.base_currency', 'CNY');
            $exchangeRate = '1';
            if ($billCurrency !== $baseCurrency) {
                $exchangeRate = (string) $this->currencyService->getExchangeRate($billCurrency);
                $totalAmount = MoneyCalculator::round(MoneyCalculator::mul($totalAmount, $exchangeRate));
                $subscriptionFee = MoneyCalculator::round(MoneyCalculator::mul($subscriptionFee, $exchangeRate));
                $usageFee = MoneyCalculator::round(MoneyCalculator::mul($usageFee, $exchangeRate));
                $discount = MoneyCalculator::round(MoneyCalculator::mul($discount, $exchangeRate));
                $taxAmount = MoneyCalculator::round(MoneyCalculator::mul($taxAmount, $exchangeRate));
            }

            $bill = Bill::create([
                'user_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
                'bill_number' => $this->generateBillNumber(),
                'subscription_fee' => MoneyCalculator::toFloat($subscriptionFee),
                'usage_fee' => MoneyCalculator::toFloat($usageFee),
                'discount' => MoneyCalculator::toFloat($discount),
                'coupon_code' => $appliedCouponCode,
                'tax' => MoneyCalculator::toFloat($taxAmount),
                'tax_rate' => $taxRate,
                'total_amount' => MoneyCalculator::toFloat($totalAmount),
                'currency' => $billCurrency,
                'exchange_rate' => MoneyCalculator::toFloat($exchangeRate, 6),
                'status' => 'pending',
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $periodEnd->copy()->addDays(7),
            ]);

            $this->createBillItems($bill, $subscription, $subscriptionFee, $usageFee, $periodStart, $periodEnd);

            if ($appliedCouponCode) {
                $coupon = Coupon::where('code', $appliedCouponCode)->first();
                if ($coupon) {
                    $this->couponService->recordUsage($coupon, $user, $bill, MoneyCalculator::toFloat($discount));
                }
            }

            event(new BillCreated($bill));
            return $bill->load('items');
        });
    }

    public function calculateSubscriptionFee(Subscription $subscription, Carbon $periodStart, Carbon $periodEnd): string
    {
        $plan = $subscription->plan;
        $days = $periodStart->diffInDays($periodEnd) + 1;
        $cycleDays = match ($plan->billing_cycle) {
            'monthly' => 30, 'quarterly' => 90, 'yearly' => 365, default => 30,
        };
        
        // BCMath 精确计算日费率
        $dailyRate = MoneyCalculator::div((string) $plan->price, (string) $cycleDays);
        return MoneyCalculator::round(MoneyCalculator::mul($dailyRate, (string) $days));
    }

    protected function calculateUsageFee(Subscription $subscription, Carbon $periodStart, Carbon $periodEnd): string
    {
        $plan = $subscription->plan;
        $includedUsage = $plan->included_usage ?? [];
        $customPricing = $plan->usage_pricing ?? [];
        $tieredPricing = $plan->tiered_pricing ?? []; // 阶梯计费配置

        $usageRecords = UsageRecord::where('subscription_id', $subscription->id)
            ->whereBetween('recorded_at', [$periodStart, $periodEnd])
            ->select('dimension_id', DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('dimension_id')
            ->get();

        $totalUsageFee = '0';
        foreach ($usageRecords as $record) {
            $dimension = MeteringDimension::find($record->dimension_id);
            if (!$dimension) continue;
            
            $totalQuantity = (string) $record->total_quantity;
            $included = (string) ($includedUsage[$dimension->code] ?? 0);
            $billableQuantity = MoneyCalculator::max(MoneyCalculator::sub($totalQuantity, $included), '0');
            
            if (MoneyCalculator::isPositive($billableQuantity)) {
                // 获取默认单价（优先自定义单价）
                $defaultUnitPrice = (string) ($customPricing[$dimension->code] ?? $dimension->unit_price);
                
                // 检查是否有阶梯计费配置
                $tieredConfig = $tieredPricing[$dimension->code] ?? null;
                
                if ($tieredConfig && !empty($tieredConfig['tiers'])) {
                    // 使用阶梯计费
                    $result = TieredPricingCalculator::calculate($billableQuantity, $tieredConfig, $defaultUnitPrice);
                    $itemFee = $result['total'];
                } else {
                    // 简单计费
                    $itemFee = MoneyCalculator::round(MoneyCalculator::mul($billableQuantity, $defaultUnitPrice));
                }
                
                $totalUsageFee = MoneyCalculator::add($totalUsageFee, $itemFee);
            }
        }
        return $totalUsageFee;
    }

    protected function createBillItems(Bill $bill, Subscription $subscription, string $subscriptionFee, string $usageFee, Carbon $periodStart, Carbon $periodEnd): void
    {
        BillItem::create([
            'bill_id' => $bill->id, 'item_type' => 'subscription',
            'description' => "订阅费用 - {$subscription->plan->name}",
            'quantity' => 1, 'unit_price' => MoneyCalculator::toFloat($subscriptionFee), 
            'amount' => MoneyCalculator::toFloat($subscriptionFee),
        ]);

        if (MoneyCalculator::isPositive($usageFee)) {
            $plan = $subscription->plan;
            $includedUsage = $plan->included_usage ?? [];
            $customPricing = $plan->usage_pricing ?? [];
            $tieredPricing = $plan->tiered_pricing ?? [];
            
            $usageRecords = UsageRecord::where('subscription_id', $subscription->id)
                ->whereBetween('recorded_at', [$periodStart, $periodEnd])
                ->select('dimension_id', DB::raw('SUM(quantity) as total_quantity'))
                ->groupBy('dimension_id')
                ->get();

            foreach ($usageRecords as $record) {
                $dimension = MeteringDimension::find($record->dimension_id);
                if (!$dimension) continue;
                
                $totalQuantity = (string) $record->total_quantity;
                $included = (string) ($includedUsage[$dimension->code] ?? 0);
                $billableQuantity = MoneyCalculator::max(MoneyCalculator::sub($totalQuantity, $included), '0');
                
                if (MoneyCalculator::isPositive($billableQuantity)) {
                    $defaultUnitPrice = (string) ($customPricing[$dimension->code] ?? $dimension->unit_price);
                    $tieredConfig = $tieredPricing[$dimension->code] ?? null;
                    
                    if ($tieredConfig && !empty($tieredConfig['tiers'])) {
                        // 阶梯计费：创建分段明细
                        $result = TieredPricingCalculator::calculate($billableQuantity, $tieredConfig, $defaultUnitPrice);
                        foreach ($result['breakdown'] as $index => $tier) {
                            BillItem::create([
                                'bill_id' => $bill->id, 'item_type' => 'usage',
                                'description' => "使用量费用 - {$dimension->name} (阶梯" . ($index + 1) . ": {$tier['from']}-{$tier['to']})",
                                'dimension_code' => $dimension->code,
                                'quantity' => $tier['quantity'],
                                'unit_price' => MoneyCalculator::toFloat($tier['unit_price'], 4),
                                'amount' => $tier['amount'],
                            ]);
                        }
                    } else {
                        // 简单计费
                        $amount = MoneyCalculator::round(MoneyCalculator::mul($billableQuantity, $defaultUnitPrice));
                        BillItem::create([
                            'bill_id' => $bill->id, 'item_type' => 'usage',
                            'description' => "使用量费用 - {$dimension->name}",
                            'dimension_code' => $dimension->code,
                            'quantity' => MoneyCalculator::toFloat($billableQuantity, 4),
                            'unit_price' => MoneyCalculator::toFloat($defaultUnitPrice, 4),
                            'amount' => MoneyCalculator::toFloat($amount),
                        ]);
                    }
                }
            }
        }
    }

    public function calculateProration(Subscription $subscription, $newPlan): array
    {
        $currentPlan = $subscription->plan;
        $endDate = $subscription->end_date;
        $now = now();
        $remainingDays = max(0, $now->diffInDays($endDate));
        $totalDays = max(1, $subscription->start_date->diffInDays($endDate));
        
        // BCMath 精确计算
        $currentDailyRate = MoneyCalculator::div((string) $currentPlan->price, (string) $totalDays);
        $currentCredit = MoneyCalculator::round(MoneyCalculator::mul($currentDailyRate, (string) $remainingDays));
        
        $newDailyRate = MoneyCalculator::div((string) $newPlan->price, (string) $totalDays);
        $newCharge = MoneyCalculator::round(MoneyCalculator::mul($newDailyRate, (string) $remainingDays));
        
        $proratedAmount = MoneyCalculator::round(MoneyCalculator::sub($newCharge, $currentCredit));

        return [
            'current_plan' => $currentPlan->name, 'new_plan' => $newPlan->name,
            'remaining_days' => $remainingDays, 
            'current_credit' => MoneyCalculator::toFloat($currentCredit),
            'new_charge' => MoneyCalculator::toFloat($newCharge), 
            'prorated_amount' => MoneyCalculator::toFloat($proratedAmount),
        ];
    }

    protected function generateBillNumber(): string
    {
        $prefix = 'BILL-' . date('Ymd') . '-';
        $random = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        while (Bill::where('bill_number', $prefix . $random)->exists()) {
            $random = str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        }
        return $prefix . $random;
    }
}
