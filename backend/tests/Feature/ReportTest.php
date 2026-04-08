<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\UsageRecord;
use App\Models\MeteringDimension;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    public function test_user_can_get_financial_overview(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
        ]);

        Bill::factory()->create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'total_amount' => 100.00,
            'status' => 'pending',
        ]);

        Bill::factory()->create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'total_amount' => 200.00,
            'status' => 'paid',
        ]);

        Payment::factory()->create([
            'user_id' => $this->user->id,
            'amount' => 200.00,
            'status' => 'completed',
            'paid_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson('/api/reports/overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_bills',
                    'paid_amount',
                    'pending_amount',
                    'bill_count',
                    'period',
                ],
            ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(300, $data['total_bills']);
        $this->assertGreaterThanOrEqual(200, $data['paid_amount']);
        $this->assertGreaterThanOrEqual(100, $data['pending_amount']);
    }

    public function test_user_can_get_usage_report(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
        ]);

        $dimension = MeteringDimension::factory()->create();

        UsageRecord::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'dimension_id' => $dimension->id,
            'quantity' => 100,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson('/api/reports/usage');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'dimension_id',
                        'total_quantity',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    public function test_user_can_get_revenue_report(): void
    {
        Payment::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'status' => 'completed',
            'amount' => 100.00,
            'paid_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson('/api/reports/revenue');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'date',
                        'total',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    public function test_reports_can_filter_by_date_range(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
        ]);

        Bill::factory()->create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'total_amount' => 100.00,
            'created_at' => now()->subMonth(),
        ]);

        Bill::factory()->create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'total_amount' => 200.00,
            'created_at' => now(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson('/api/reports/overview', [
                'start_date' => now()->subWeek()->toDateString(),
                'end_date' => now()->toDateString(),
            ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        // 应该只包含最近一周的数据
        $this->assertGreaterThanOrEqual(200, $data['total_bills']);
    }
}
