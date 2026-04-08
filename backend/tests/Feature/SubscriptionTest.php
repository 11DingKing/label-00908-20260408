<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    public function test_user_can_get_subscription_plans(): void
    {
        SubscriptionPlan::factory()->count(3)->create(['is_active' => true]);
        SubscriptionPlan::factory()->create(['is_active' => false]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson('/api/subscriptions/plans');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_get_single_subscription_plan(): void
    {
        $plan = SubscriptionPlan::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson("/api/subscriptions/plans/{$plan->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                ],
            ]);
    }

    public function test_user_can_create_subscription(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'billing_cycle' => 'monthly',
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->postJson('/api/subscriptions', [
                'plan_id' => $plan->id,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => '订阅创建成功',
            ]);

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
    }

    public function test_creating_subscription_cancels_existing_active_subscription(): void
    {
        $oldPlan = SubscriptionPlan::factory()->create();
        $oldSubscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $oldPlan->id,
            'status' => 'active',
        ]);

        $newPlan = SubscriptionPlan::factory()->create();
        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->postJson('/api/subscriptions', [
                'plan_id' => $newPlan->id,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $oldSubscription->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $this->user->id,
            'plan_id' => $newPlan->id,
            'status' => 'active',
        ]);
    }

    public function test_user_can_get_their_subscriptions(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        Subscription::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson('/api/subscriptions');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_get_single_subscription(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson("/api/subscriptions/{$subscription->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $subscription->id,
                ],
            ]);
    }

    public function test_user_cannot_get_other_users_subscription(): void
    {
        $otherUser = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $otherUser->id,
            'plan_id' => $plan->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson("/api/subscriptions/{$subscription->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_cancel_subscription(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->postJson("/api/subscriptions/{$subscription->id}/cancel", [
                'reason' => '不再需要',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => '订阅已取消',
            ]);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_user_cannot_cancel_already_cancelled_subscription(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
            'status' => 'cancelled',
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->postJson("/api/subscriptions/{$subscription->id}/cancel");

        $response->assertStatus(404);
    }

    public function test_create_subscription_requires_valid_plan(): void
    {
        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->postJson('/api/subscriptions', [
                'plan_id' => 99999,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan_id']);
    }
}
