<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BillingControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    public function test_user_can_trigger_billing_calculation(): void
    {
        Queue::fake();

        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->postJson('/api/billing/calculate');

        $response->assertStatus(200)
            ->assertJson([
                'message' => '计费计算任务已提交，请稍后查看账单',
            ]);

        Queue::assertPushed(\App\Modules\Billing\Jobs\CalculateBillJob::class);
    }

    public function test_billing_calculation_requires_active_subscription(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
            'status' => 'cancelled',
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->postJson('/api/billing/calculate');

        $response->assertStatus(400)
            ->assertJson([
                'message' => '用户没有活跃的订阅',
            ]);
    }

    public function test_billing_calculation_can_specify_period(): void
    {
        Queue::fake();

        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $periodStart = now()->startOfMonth()->toDateString();
        $periodEnd = now()->endOfMonth()->toDateString();

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->postJson('/api/billing/calculate', [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ]);

        $response->assertStatus(200);

        Queue::assertPushed(\App\Modules\Billing\Jobs\CalculateBillJob::class, function ($job) use ($periodStart, $periodEnd) {
            return $job->periodStart->toDateString() === $periodStart &&
                   $job->periodEnd->toDateString() === $periodEnd;
        });
    }
}
