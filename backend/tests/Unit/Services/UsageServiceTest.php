<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\MeteringDimension;
use App\Models\UsageRecord;
use App\Modules\Subscription\Services\UsageService;
use App\Modules\Subscription\Models\UsageRecord as ModuleUsageRecord;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected UsageService $usageService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usageService = new UsageService();
    }

    public function test_record_usage_creates_usage_record(): void
    {
        $user = User::factory()->create();
        $dimension = MeteringDimension::factory()->create([
            'code' => 'api_calls',
            'is_active' => true,
        ]);

        $record = $this->usageService->recordUsage($user, 'api_calls', 100);

        $this->assertInstanceOf(ModuleUsageRecord::class, $record);
        $this->assertEquals($user->id, $record->user_id);
        $this->assertEquals($dimension->id, $record->dimension_id);
        $this->assertEquals(100, $record->quantity);
    }

    public function test_record_usage_with_active_subscription(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        $dimension = MeteringDimension::factory()->create([
            'code' => 'api_calls',
            'is_active' => true,
        ]);

        $record = $this->usageService->recordUsage($user, 'api_calls', 100);

        $this->assertEquals($subscription->id, $record->subscription_id);
    }

    public function test_record_usage_without_subscription(): void
    {
        $user = User::factory()->create();
        $dimension = MeteringDimension::factory()->create([
            'code' => 'api_calls',
            'is_active' => true,
        ]);

        $record = $this->usageService->recordUsage($user, 'api_calls', 100);

        $this->assertNull($record->subscription_id);
    }

    public function test_record_usage_with_metadata(): void
    {
        $user = User::factory()->create();
        $dimension = MeteringDimension::factory()->create([
            'code' => 'api_calls',
            'is_active' => true,
        ]);
        $metadata = ['source' => 'api', 'endpoint' => '/users'];

        $record = $this->usageService->recordUsage($user, 'api_calls', 100, $metadata);

        $this->assertEquals($metadata, $record->metadata);
    }

    public function test_record_usage_throws_exception_for_invalid_dimension(): void
    {
        $user = User::factory()->create();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->usageService->recordUsage($user, 'invalid_dimension', 100);
    }

    public function test_record_usage_throws_exception_for_inactive_dimension(): void
    {
        $user = User::factory()->create();
        MeteringDimension::factory()->create([
            'code' => 'api_calls',
            'is_active' => false,
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->usageService->recordUsage($user, 'api_calls', 100);
    }

    public function test_get_usage_statistics_returns_correct_data(): void
    {
        $user = User::factory()->create();
        $dimension = MeteringDimension::factory()->create([
            'code' => 'api_calls',
            'name' => 'API调用',
            'unit' => '次',
        ]);

        UsageRecord::factory()->create([
            'user_id' => $user->id,
            'dimension_id' => $dimension->id,
            'quantity' => 100,
        ]);
        UsageRecord::factory()->create([
            'user_id' => $user->id,
            'dimension_id' => $dimension->id,
            'quantity' => 200,
        ]);

        $statistics = $this->usageService->getUsageStatistics($user);

        $this->assertCount(1, $statistics);
        $this->assertEquals('api_calls', $statistics[0]['dimension_code']);
        $this->assertEquals('API调用', $statistics[0]['dimension_name']);
        $this->assertEquals('次', $statistics[0]['unit']);
        $this->assertEquals(300, $statistics[0]['total_quantity']);
        $this->assertEquals(2, $statistics[0]['record_count']);
    }

    public function test_get_usage_statistics_with_date_filter(): void
    {
        $user = User::factory()->create();
        $dimension = MeteringDimension::factory()->create(['code' => 'api_calls']);

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

        $statistics = $this->usageService->getUsageStatistics(
            $user,
            Carbon::now()->subWeek(),
            Carbon::now()->addDay()
        );

        $this->assertCount(1, $statistics);
        $this->assertEquals(200, $statistics[0]['total_quantity']);
    }

    public function test_get_usage_statistics_multiple_dimensions(): void
    {
        $user = User::factory()->create();
        $dimension1 = MeteringDimension::factory()->create(['code' => 'api_calls']);
        $dimension2 = MeteringDimension::factory()->create(['code' => 'storage']);

        UsageRecord::factory()->create([
            'user_id' => $user->id,
            'dimension_id' => $dimension1->id,
            'quantity' => 100,
        ]);
        UsageRecord::factory()->create([
            'user_id' => $user->id,
            'dimension_id' => $dimension2->id,
            'quantity' => 50,
        ]);

        $statistics = $this->usageService->getUsageStatistics($user);

        $this->assertCount(2, $statistics);
    }

    public function test_get_usage_statistics_empty_for_no_records(): void
    {
        $user = User::factory()->create();

        $statistics = $this->usageService->getUsageStatistics($user);

        $this->assertEmpty($statistics);
    }
}
