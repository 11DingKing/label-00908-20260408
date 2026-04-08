<?php

namespace Tests\Unit\Services;

use App\Models\Bill;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\MeteringDimension;
use App\Models\UsageRecord;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reportService = new ReportService();
    }

    public function test_get_financial_overview_returns_correct_structure(): void
    {
        $user = User::factory()->create();

        $overview = $this->reportService->getFinancialOverview($user->id);

        $this->assertArrayHasKey('period', $overview);
        $this->assertArrayHasKey('bills', $overview);
        $this->assertArrayHasKey('payments', $overview);
        $this->assertArrayHasKey('subscription_revenue', $overview);
        $this->assertArrayHasKey('usage_revenue', $overview);
    }

    public function test_get_financial_overview_calculates_paid_amount(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        Bill::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'status' => 'paid',
            'total_amount' => 100.00,
        ]);
        Bill::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'status' => 'paid',
            'total_amount' => 200.00,
        ]);

        $overview = $this->reportService->getFinancialOverview($user->id);

        $this->assertEquals(300.00, $overview['bills']['paid_amount']);
    }

    public function test_get_financial_overview_calculates_pending_amount(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        Bill::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'status' => 'pending',
            'total_amount' => 150.00,
        ]);

        $overview = $this->reportService->getFinancialOverview($user->id);

        $this->assertEquals(150.00, $overview['bills']['pending_amount']);
    }

    public function test_get_financial_overview_counts_bills(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        Bill::factory()->count(3)->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
        ]);

        $overview = $this->reportService->getFinancialOverview($user->id);

        $this->assertEquals(3, $overview['bills']['total_count']);
    }

    public function test_get_financial_overview_calculates_subscription_revenue(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        Bill::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'subscription_fee' => 100.00,
            'usage_fee' => 0,
        ]);

        $overview = $this->reportService->getFinancialOverview($user->id);

        $this->assertEquals(100.00, $overview['subscription_revenue']);
    }

    public function test_get_usage_report_returns_correct_structure(): void
    {
        $user = User::factory()->create();

        $report = $this->reportService->getUsageReport($user->id);

        $this->assertArrayHasKey('period', $report);
        $this->assertArrayHasKey('usage', $report);
        $this->assertIsArray($report['usage']);
    }

    public function test_get_usage_report_groups_by_dimension(): void
    {
        $user = User::factory()->create();
        $dimension1 = MeteringDimension::factory()->create(['code' => 'api_calls']);
        $dimension2 = MeteringDimension::factory()->create(['code' => 'storage']);

        UsageRecord::factory()->create([
            'user_id' => $user->id,
            'dimension_id' => $dimension1->id,
            'quantity' => 100,
            'recorded_at' => now(),
        ]);
        UsageRecord::factory()->create([
            'user_id' => $user->id,
            'dimension_id' => $dimension2->id,
            'quantity' => 50,
            'recorded_at' => now(),
        ]);

        $report = $this->reportService->getUsageReport($user->id);

        $this->assertCount(2, $report['usage']);
    }

    public function test_get_usage_report_with_date_filter(): void
    {
        $user = User::factory()->create();
        $dimension = MeteringDimension::factory()->create();

        UsageRecord::factory()->create([
            'user_id' => $user->id,
            'dimension_id' => $dimension->id,
            'quantity' => 100,
            'recorded_at' => Carbon::now()->subMonth(),
        ]);
        UsageRecord::factory()->create([
            'user_id' => $user->id,
            'dimension_id' => $dimension->id,
            'quantity' => 200,
            'recorded_at' => Carbon::now(),
        ]);

        $report = $this->reportService->getUsageReport(
            $user->id,
            Carbon::now()->subWeek(),
            Carbon::now()->addDay()
        );

        $this->assertCount(1, $report['usage']);
        $this->assertEquals(200, $report['usage'][0]['total_quantity']);
    }

    public function test_get_subscription_stats_returns_correct_structure(): void
    {
        $stats = $this->reportService->getSubscriptionStats();

        $this->assertArrayHasKey('by_status', $stats);
        $this->assertArrayHasKey('by_plan', $stats);
        $this->assertArrayHasKey('total', $stats);
    }

    public function test_get_subscription_stats_counts_by_status(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();

        Subscription::factory()->count(3)->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        Subscription::factory()->count(2)->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'cancelled',
        ]);

        $stats = $this->reportService->getSubscriptionStats();

        $this->assertEquals(3, $stats['by_status']['active']);
        $this->assertEquals(2, $stats['by_status']['cancelled']);
    }

    public function test_get_user_stats_returns_correct_structure(): void
    {
        $stats = $this->reportService->getUserStats();

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('active', $stats);
        $this->assertArrayHasKey('subscribed', $stats);
        $this->assertArrayHasKey('new_this_month', $stats);
        $this->assertArrayHasKey('subscription_rate', $stats);
    }

    public function test_get_user_stats_counts_users(): void
    {
        User::factory()->count(5)->create(['status' => 'active']);
        User::factory()->count(2)->create(['status' => 'inactive']);

        $stats = $this->reportService->getUserStats();

        $this->assertEquals(7, $stats['total']);
        $this->assertEquals(5, $stats['active']);
    }
}
