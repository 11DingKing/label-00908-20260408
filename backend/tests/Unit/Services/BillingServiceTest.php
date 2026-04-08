<?php

namespace Tests\Unit\Services;

use App\Models\Bill;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\MeteringDimension;
use App\Models\UsageRecord;
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

    public function test_calculate_subscription_fee_monthly(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create([
            'price' => 300.00,
            'billing_cycle' => 'monthly',
        ]);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $periodStart = Carbon::create(2024, 1, 1);
        $periodEnd = Carbon::create(2024, 1, 31);
        $days = 31;

        $fee = $this->billingService->calculateSubscriptionFee($subscription, $periodStart, $periodEnd);

        $expectedFee = round((300 / 30) * $days, 2);
        $this->assertEquals($expectedFee, $fee);
    }

    public function test_calculate_subscription_fee_quarterly(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create([
            'price' => 900.00,
            'billing_cycle' => 'quarterly',
        ]);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $periodStart = Carbon::create(2024, 1, 1);
        $periodEnd = Carbon::create(2024, 1, 31);
        $days = 31;

        $fee = $this->billingService->calculateSubscriptionFee($subscription, $periodStart, $periodEnd);

        $expectedFee = round((900 / 90) * $days, 2);
        $this->assertEquals($expectedFee, $fee);
    }

    public function test_calculate_subscription_fee_yearly(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create([
            'price' => 3650.00,
            'billing_cycle' => 'yearly',
        ]);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $periodStart = Carbon::create(2024, 1, 1);
        $periodEnd = Carbon::create(2024, 1, 31);
        $days = 31;

        $fee = $this->billingService->calculateSubscriptionFee($subscription, $periodStart, $periodEnd);

        $expectedFee = round((3650 / 365) * $days, 2);
        $this->assertEquals($expectedFee, $fee);
    }

    public function test_calculate_bill_creates_bill_record(): void
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

        $this->assertInstanceOf(Bill::class, $bill);
        $this->assertEquals($user->id, $bill->user_id);
        $this->assertEquals($subscription->id, $bill->subscription_id);
        $this->assertEquals('pending', $bill->status);
        $this->assertNotNull($bill->bill_number);
    }

    public function test_calculate_bill_with_usage_exceeding_included(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create([
            'price' => 100.00,
            'billing_cycle' => 'monthly',
            'included_usage' => ['api_calls' => 500],
        ]);
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);
        $dimension = MeteringDimension::factory()->create([
            'code' => 'api_calls',
            'unit_price' => 0.02,
        ]);

        $periodStart = Carbon::now()->startOfMonth();
        $periodEnd = Carbon::now()->endOfMonth();

        UsageRecord::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'dimension_id' => $dimension->id,
            'quantity' => 1000,
            'recorded_at' => $periodStart->copy()->addDay(),
        ]);

        $bill = $this->billingService->calculateBill($subscription, $periodStart, $periodEnd);

        $this->assertEquals(10.00, (float) $bill->usage_fee);
    }

    public function test_calculate_bill_no_usage_fee_within_included(): void
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
        ]);
        $dimension = MeteringDimension::factory()->create([
            'code' => 'api_calls',
            'unit_price' => 0.02,
        ]);

        $periodStart = Carbon::now()->startOfMonth();
        $periodEnd = Carbon::now()->endOfMonth();

        UsageRecord::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'dimension_id' => $dimension->id,
            'quantity' => 500,
            'recorded_at' => $periodStart->copy()->addDay(),
        ]);

        $bill = $this->billingService->calculateBill($subscription, $periodStart, $periodEnd);

        $this->assertEquals(0, (float) $bill->usage_fee);
    }

    public function test_calculate_bill_sets_due_date(): void
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

        $this->assertNotEmpty($bill->items);
        $this->assertTrue($bill->items->contains('item_type', 'subscription'));
    }

    public function test_bill_number_is_unique(): void
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
}
