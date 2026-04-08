<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\UsageRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_is_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($user->isAdmin());
    }

    public function test_user_is_active(): void
    {
        $activeUser = User::factory()->create(['status' => 'active']);
        $inactiveUser = User::factory()->create(['status' => 'inactive']);
        $suspendedUser = User::factory()->create(['status' => 'suspended']);

        $this->assertTrue($activeUser->isActive());
        $this->assertFalse($inactiveUser->isActive());
        $this->assertFalse($suspendedUser->isActive());
    }

    public function test_user_has_subscriptions_relationship(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        Subscription::factory()->count(3)->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $this->assertCount(3, $user->subscriptions);
        $this->assertInstanceOf(Subscription::class, $user->subscriptions->first());
    }

    public function test_user_has_active_subscription_relationship(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'cancelled',
        ]);
        
        $activeSubscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $this->assertNotNull($user->activeSubscription);
        $this->assertEquals($activeSubscription->id, $user->activeSubscription->id);
    }

    public function test_user_has_bills_relationship(): void
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

        $this->assertCount(2, $user->bills);
        $this->assertInstanceOf(Bill::class, $user->bills->first());
    }

    public function test_user_has_payments_relationship(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);
        $bill = Bill::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
        ]);
        Payment::factory()->count(2)->create([
            'user_id' => $user->id,
            'bill_id' => $bill->id,
        ]);

        $this->assertCount(2, $user->payments);
        $this->assertInstanceOf(Payment::class, $user->payments->first());
    }

    public function test_user_jwt_identifier(): void
    {
        $user = User::factory()->create();

        $this->assertEquals($user->id, $user->getJWTIdentifier());
    }

    public function test_user_jwt_custom_claims(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $claims = $user->getJWTCustomClaims();

        $this->assertArrayHasKey('role', $claims);
        $this->assertEquals('admin', $claims['role']);
    }
}
