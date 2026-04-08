<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\MeteringDimension;
use App\Models\UsageRecord;
use App\Models\Bill;
use App\Modules\Billing\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BillingService $billingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->billingService = app(BillingService::class);
    }

    public function test_calculate_bill_with_subscription_fee_only(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create([
            'price' => 100.00,
            'billing_cycle' => 'monthly',
            'included_usage' => [],
        ]);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $periodStart = Carbon::now()->startOfMonth();
        $periodEnd = Carbon::now()->endOfMonth();
        $days = $periodStart->diffInDays($periodEnd) + 1;

        $bill = $this->billingService->calculateBill($subscription, $periodStart, $periodEnd);

        $this->assertInstanceOf(Bill::class, $bill);
        $this->assertEquals($user->id, $bill->user_id);
        $this->assertEquals($subscription->id, $bill->subscription_id);
        $this->assertEquals('pending', $bill->status);

        $expectedFee = round((100 / 30) * $days, 2);
        $this->assertEquals($expectedFee, (float) $bill->subscription_fee);
        $this->assertEquals(0, (float) $bill->usage_fee);
    }

    public function test_calculate_bill_with_usage_fee(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create([
            'price' => 100.00,
            'billing_cycle' => 'monthly',
            'included_usage' => ['api_calls' => 1000],
        ]);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $dimension = MeteringDimension::factory()->create([
            'code' => 'api_calls',
            'unit_price' => 0.01,
        ]);

        UsageRecord::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'dimension_id' => $dimension->id,
            'quantity' => 1500,
            'recorded_at' => Carbon::now()->startOfMonth()->addDay(),
        ]);

        $periodStart = Carbon::now()->startOfMonth();
        $periodEnd = Carbon::now()->endOfMonth();

        $bill = $this->billingService->calculateBill($subscription, $periodStart, $periodEnd);

        $this->assertGreaterThan(0, (float) $bill->subscription_fee);
        $this->assertEquals(5.00, (float) $bill->usage_fee);
    }

    public function test_calculate_bill_creates_bill_items(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create([
            'price' => 100.00,
            'billing_cycle' => 'monthly',
        ]);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $periodStart = Carbon::now()->startOfMonth();
        $periodEnd = Carbon::now()->endOfMonth();

        $bill = $this->billingService->calculateBill($subscription, $periodStart, $periodEnd);

        $items = $bill->items;
        $this->assertNotEmpty($items);
        $this->assertTrue($items->contains('item_type', 'subscription'));
    }

    public function test_calculate_bill_generates_unique_bill_number(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $periodStart = Carbon::now()->startOfMonth();
        $periodEnd = Carbon::now()->endOfMonth();

        $bill1 = $this->billingService->calculateBill($subscription, $periodStart, $periodEnd);
        $bill2 = $this->billingService->calculateBill($subscription, $periodStart, $periodEnd);

        $this->assertNotEquals($bill1->bill_number, $bill2->bill_number);
    }

    public function test_calculate_bill_sets_correct_due_date(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $periodStart = Carbon::now()->startOfMonth();
        $periodEnd = Carbon::now()->endOfMonth();

        $bill = $this->billingService->calculateBill($subscription, $periodStart, $periodEnd);

        $expectedDueDate = $periodEnd->copy()->addDays(7);
        $this->assertEquals($expectedDueDate->toDateString(), $bill->due_date->toDateString());
    }

    public function test_calculate_subscription_fee_for_different_billing_cycles(): void
    {
        $user = User::factory()->create();

        $monthlyPlan = SubscriptionPlan::factory()->create([
            'price' => 100.00,
            'billing_cycle' => 'monthly',
        ]);
        $monthlySubscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $monthlyPlan->id,
        ]);

        $periodStart = Carbon::now()->startOfMonth();
        $periodEnd = Carbon::now()->endOfMonth();
        $days = $periodStart->diffInDays($periodEnd) + 1;

        $fee = $this->billingService->calculateSubscriptionFee(
            $monthlySubscription, $periodStart, $periodEnd
        );

        $expectedFee = round((100 / 30) * $days, 2);
        $this->assertEquals($expectedFee, $fee);

        $yearlyPlan = SubscriptionPlan::factory()->create([
            'price' => 1000.00,
            'billing_cycle' => 'yearly',
        ]);
        $yearlySubscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $yearlyPlan->id,
        ]);

        $fee = $this->billingService->calculateSubscriptionFee(
            $yearlySubscription, $periodStart, $periodEnd
        );

        $expectedFee = round((1000 / 365) * $days, 2);
        $this->assertEquals($expectedFee, $fee);
    }
}
