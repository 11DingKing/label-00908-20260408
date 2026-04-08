<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $otherUser;
    protected User $admin;
    protected string $userToken;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'user']);
        $this->otherUser = User::factory()->create(['role' => 'user']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->userToken = JWTAuth::fromUser($this->user);
        $this->adminToken = JWTAuth::fromUser($this->admin);
    }

    // === Bill Policy Tests ===

    public function test_user_can_view_own_bill(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
        ]);
        $bill = Bill::factory()->create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->userToken")
            ->getJson("/api/bills/{$bill->id}");

        $response->assertStatus(200);
    }

    public function test_user_cannot_view_other_users_bill(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->otherUser->id,
            'plan_id' => $plan->id,
        ]);
        $bill = Bill::factory()->create([
            'user_id' => $this->otherUser->id,
            'subscription_id' => $subscription->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->userToken")
            ->getJson("/api/bills/{$bill->id}");

        $response->assertStatus(403);
    }

    public function test_admin_can_view_any_bill(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->otherUser->id,
            'plan_id' => $plan->id,
        ]);
        $bill = Bill::factory()->create([
            'user_id' => $this->otherUser->id,
            'subscription_id' => $subscription->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->adminToken")
            ->getJson("/api/bills/{$bill->id}");

        $response->assertStatus(200);
    }

    // === Payment Policy Tests ===

    public function test_user_can_view_own_payment(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
        ]);
        $bill = Bill::factory()->create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
        ]);
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'bill_id' => $bill->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->userToken")
            ->getJson("/api/payments/{$payment->id}");

        $response->assertStatus(200);
    }

    public function test_user_cannot_view_other_users_payment(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->otherUser->id,
            'plan_id' => $plan->id,
        ]);
        $bill = Bill::factory()->create([
            'user_id' => $this->otherUser->id,
            'subscription_id' => $subscription->id,
        ]);
        $payment = Payment::factory()->create([
            'user_id' => $this->otherUser->id,
            'bill_id' => $bill->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->userToken")
            ->getJson("/api/payments/{$payment->id}");

        $response->assertStatus(403);
    }

    // === Subscription Policy Tests ===

    public function test_user_can_view_own_subscription(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->userToken")
            ->getJson("/api/subscriptions/{$subscription->id}");

        $response->assertStatus(200);
    }

    public function test_user_cannot_view_other_users_subscription(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->otherUser->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->userToken")
            ->getJson("/api/subscriptions/{$subscription->id}");

        $response->assertStatus(403);
    }

    public function test_admin_can_view_any_subscription(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->otherUser->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->adminToken")
            ->getJson("/api/subscriptions/{$subscription->id}");

        $response->assertStatus(200);
    }

    // === Soft Delete Tests ===

    public function test_models_use_soft_deletes(): void
    {
        $this->assertTrue(
            method_exists(new User(), 'trashed'),
            'User model should use SoftDeletes'
        );
        $this->assertTrue(
            method_exists(new Subscription(), 'trashed'),
            'Subscription model should use SoftDeletes'
        );
        $this->assertTrue(
            method_exists(new Bill(), 'trashed'),
            'Bill model should use SoftDeletes'
        );
        $this->assertTrue(
            method_exists(new Payment(), 'trashed'),
            'Payment model should use SoftDeletes'
        );
        $this->assertTrue(
            method_exists(new Refund(), 'trashed'),
            'Refund model should use SoftDeletes'
        );
    }
}
