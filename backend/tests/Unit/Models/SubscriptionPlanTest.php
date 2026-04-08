<?php

namespace Tests\Unit\Models;

use App\Models\SubscriptionPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Modules\Subscription\Models\Subscription as ModuleSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_has_subscriptions_relationship(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $user = User::factory()->create();
        Subscription::factory()->count(3)->create([
            'plan_id' => $plan->id,
            'user_id' => $user->id,
        ]);

        $this->assertCount(3, $plan->subscriptions);
        $this->assertInstanceOf(ModuleSubscription::class, $plan->subscriptions->first());
    }

    public function test_plan_active_scope(): void
    {
        SubscriptionPlan::factory()->count(3)->create(['is_active' => true]);
        SubscriptionPlan::factory()->count(2)->create(['is_active' => false]);

        $activePlans = SubscriptionPlan::active()->get();

        $this->assertCount(3, $activePlans);
    }

    public function test_plan_features_cast_to_array(): void
    {
        $features = ['feature1', 'feature2', 'feature3'];
        $plan = SubscriptionPlan::factory()->create(['features' => $features]);

        $this->assertIsArray($plan->features);
        $this->assertEquals($features, $plan->features);
    }

    public function test_plan_included_usage_cast_to_array(): void
    {
        $includedUsage = ['api_calls' => 1000, 'storage' => 10];
        $plan = SubscriptionPlan::factory()->create(['included_usage' => $includedUsage]);

        $this->assertIsArray($plan->included_usage);
        $this->assertEquals($includedUsage, $plan->included_usage);
    }

    public function test_plan_price_cast_to_decimal(): void
    {
        $plan = SubscriptionPlan::factory()->create(['price' => 99.99]);

        $this->assertEquals('99.99', $plan->price);
    }

    public function test_plan_billing_cycles(): void
    {
        $monthlyPlan = SubscriptionPlan::factory()->create(['billing_cycle' => 'monthly']);
        $quarterlyPlan = SubscriptionPlan::factory()->create(['billing_cycle' => 'quarterly']);
        $yearlyPlan = SubscriptionPlan::factory()->create(['billing_cycle' => 'yearly']);

        $this->assertEquals('monthly', $monthlyPlan->billing_cycle);
        $this->assertEquals('quarterly', $quarterlyPlan->billing_cycle);
        $this->assertEquals('yearly', $yearlyPlan->billing_cycle);
    }
}
