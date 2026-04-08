<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionUpgradeDowngradeTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    public function test_user_can_upgrade_subscription(): void
    {
        $basicPlan = SubscriptionPlan::factory()->create(['price' => 99.00]);
        $proPlan = SubscriptionPlan::factory()->create(['price' => 299.00]);

        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $basicPlan->id,
            'status' => 'active',
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(20),
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->putJson("/api/subscriptions/{$subscription->id}/upgrade", [
                'plan_id' => $proPlan->id,
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => '订阅升级成功']);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'plan_id' => $proPlan->id,
        ]);
    }

    public function test_upgrade_rejects_lower_price_plan(): void
    {
        $proPlan = SubscriptionPlan::factory()->create(['price' => 299.00]);
        $basicPlan = SubscriptionPlan::factory()->create(['price' => 99.00]);

        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->putJson("/api/subscriptions/{$subscription->id}/upgrade", [
                'plan_id' => $basicPlan->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_upgrade_requires_plan_id(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->putJson("/api/subscriptions/{$subscription->id}/upgrade", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['plan_id']);
    }

    public function test_upgrade_requires_active_subscription(): void
    {
        $plan = SubscriptionPlan::factory()->create(['price' => 99.00]);
        $newPlan = SubscriptionPlan::factory()->create(['price' => 299.00]);

        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
            'status' => 'cancelled',
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->putJson("/api/subscriptions/{$subscription->id}/upgrade", [
                'plan_id' => $newPlan->id,
            ]);

        $response->assertStatus(404);
    }

    public function test_user_can_downgrade_subscription(): void
    {
        $proPlan = SubscriptionPlan::factory()->create(['price' => 299.00]);
        $basicPlan = SubscriptionPlan::factory()->create(['price' => 99.00]);

        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $proPlan->id,
            'status' => 'active',
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(20),
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->putJson("/api/subscriptions/{$subscription->id}/downgrade", [
                'plan_id' => $basicPlan->id,
            ]);

        $response->assertStatus(200)
            ->assertJson(['message' => '订阅降级成功']);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'plan_id' => $basicPlan->id,
        ]);
    }

    public function test_downgrade_rejects_higher_price_plan(): void
    {
        $basicPlan = SubscriptionPlan::factory()->create(['price' => 99.00]);
        $proPlan = SubscriptionPlan::factory()->create(['price' => 299.00]);

        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $basicPlan->id,
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->putJson("/api/subscriptions/{$subscription->id}/downgrade", [
                'plan_id' => $proPlan->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_user_cannot_upgrade_other_users_subscription(): void
    {
        $otherUser = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create(['price' => 99.00]);
        $newPlan = SubscriptionPlan::factory()->create(['price' => 299.00]);

        $subscription = Subscription::factory()->create([
            'user_id' => $otherUser->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->putJson("/api/subscriptions/{$subscription->id}/upgrade", [
                'plan_id' => $newPlan->id,
            ]);

        $response->assertStatus(403);
    }
}
