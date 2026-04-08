<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\MeteringDimension;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingRulesTest extends TestCase
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

    public function test_user_can_get_billing_rules(): void
    {
        SubscriptionPlan::factory()->count(2)->create(['is_active' => true]);
        MeteringDimension::factory()->count(3)->create(['is_active' => true]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson('/api/billing/rules');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'billing_cycles',
                    'plans',
                    'metering_dimensions',
                    'rules',
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(3, $data['billing_cycles']);
        $this->assertCount(2, $data['plans']);
        $this->assertCount(3, $data['metering_dimensions']);
        $this->assertArrayHasKey('proration', $data['rules']);
        $this->assertArrayHasKey('overdue_grace_period_days', $data['rules']);
        $this->assertArrayHasKey('usage_billing', $data['rules']);
    }

    public function test_billing_rules_only_returns_active_plans(): void
    {
        SubscriptionPlan::factory()->create(['is_active' => true]);
        SubscriptionPlan::factory()->create(['is_active' => false]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson('/api/billing/rules');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.plans'));
    }

    public function test_billing_rules_only_returns_active_dimensions(): void
    {
        MeteringDimension::factory()->count(2)->create(['is_active' => true]);
        MeteringDimension::factory()->create(['is_active' => false]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson('/api/billing/rules');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.metering_dimensions'));
    }

    public function test_billing_rules_accessible_with_authentication(): void
    {
        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson('/api/billing/rules');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['billing_cycles', 'plans', 'metering_dimensions', 'rules'],
            ]);
    }
}
