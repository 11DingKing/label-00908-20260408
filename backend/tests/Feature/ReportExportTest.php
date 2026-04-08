<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\MeteringDimension;
use App\Models\UsageRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
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

    public function test_user_can_export_bills_csv(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
        ]);

        Bill::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson('/api/reports/export?type=bills');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_user_can_export_payments_csv(): void
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

        Payment::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'bill_id' => $bill->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson('/api/reports/export?type=payments');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_user_can_export_usage_csv(): void
    {
        $dimension = MeteringDimension::factory()->create();

        UsageRecord::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'dimension_id' => $dimension->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson('/api/reports/export?type=usage');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }

    public function test_export_defaults_to_bills_type(): void
    {
        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson('/api/reports/export');

        $response->assertStatus(200);
        $this->assertStringContainsString('report-bills', $response->headers->get('Content-Disposition'));
    }

    public function test_export_supports_date_range_filter(): void
    {
        $startDate = now()->subMonth()->toDateString();
        $endDate = now()->toDateString();

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson("/api/reports/export?type=bills&start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200);
    }
}
