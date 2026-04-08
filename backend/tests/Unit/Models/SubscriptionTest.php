<?php

namespace Tests\Unit\Models;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Bill;
use App\Models\UsageRecord;
use App\Models\MeteringDimension;
use App\Modules\Subscription\Models\SubscriptionPlan as ModuleSubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $this->assertInstanceOf(User::class, $subscription->user);
        $this->assertEquals($user->id, $subscription->user->id);
    }

    public function test_subscription_belongs_to_plan(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $this->assertInstanceOf(ModuleSubscriptionPlan::class, $subscription->plan);
        $this->assertEquals($plan->id, $subscription->plan->id);
    }

    public function test_subscription_has_bills_relationship(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);
        Bill::factory()->count(2)->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
        ]);

        $this->assertCount(2, $subscription->bills);
    }

    public function test_subscription_is_active(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();

        $activeSubscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'end_date' => now()->addMonth(),
        ]);
        $cancelledSubscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'cancelled',
        ]);

        $this->assertTrue($activeSubscription->isActive());
        $this->assertFalse($cancelledSubscription->isActive());
    }

    public function test_subscription_status_check(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'cancelled',
        ]);

        $this->assertEquals('cancelled', $subscription->status);
    }

    public function test_subscription_cancel_method(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $subscription->cancel('用户主动取消');

        $this->assertEquals('cancelled', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertEquals('用户主动取消', $subscription->cancellation_reason);
        $this->assertFalse($subscription->auto_renew);
    }

    public function test_subscription_has_usage_records_relationship(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);
        $dimension = MeteringDimension::factory()->create();

        UsageRecord::factory()->count(3)->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'dimension_id' => $dimension->id,
        ]);

        $this->assertCount(3, $subscription->usageRecords);
    }

    public function test_subscription_dates_cast(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $subscription->start_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $subscription->end_date);
    }

    public function test_subscription_auto_renew_cast_to_boolean(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();

        $autoRenewSubscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'auto_renew' => true,
        ]);
        $noAutoRenewSubscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'auto_renew' => false,
        ]);

        $this->assertTrue($autoRenewSubscription->auto_renew);
        $this->assertFalse($noAutoRenewSubscription->auto_renew);
    }
}
