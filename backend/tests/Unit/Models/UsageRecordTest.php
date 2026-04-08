<?php

namespace Tests\Unit\Models;

use App\Models\UsageRecord;
use App\Models\MeteringDimension;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Modules\Subscription\Models\MeteringDimension as ModuleMeteringDimension;
use App\Modules\Subscription\Models\Subscription as ModuleSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_record_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $dimension = MeteringDimension::factory()->create();
        $record = UsageRecord::factory()->create([
            'user_id' => $user->id,
            'dimension_id' => $dimension->id,
        ]);

        $this->assertInstanceOf(User::class, $record->user);
        $this->assertEquals($user->id, $record->user->id);
    }

    public function test_usage_record_belongs_to_dimension(): void
    {
        $user = User::factory()->create();
        $dimension = MeteringDimension::factory()->create();
        $record = UsageRecord::factory()->create([
            'user_id' => $user->id,
            'dimension_id' => $dimension->id,
        ]);

        $this->assertInstanceOf(ModuleMeteringDimension::class, $record->dimension);
        $this->assertEquals($dimension->id, $record->dimension->id);
    }

    public function test_usage_record_belongs_to_subscription(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);
        $dimension = MeteringDimension::factory()->create();
        $record = UsageRecord::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'dimension_id' => $dimension->id,
        ]);

        $this->assertInstanceOf(ModuleSubscription::class, $record->subscription);
        $this->assertEquals($subscription->id, $record->subscription->id);
    }

    public function test_usage_record_can_have_null_subscription(): void
    {
        $user = User::factory()->create();
        $dimension = MeteringDimension::factory()->create();
        $record = UsageRecord::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => null,
            'dimension_id' => $dimension->id,
        ]);

        $this->assertNull($record->subscription);
    }

    public function test_usage_record_quantity_cast_to_decimal(): void
    {
        $user = User::factory()->create();
        $dimension = MeteringDimension::factory()->create();
        $record = UsageRecord::factory()->create([
            'user_id' => $user->id,
            'dimension_id' => $dimension->id,
            'quantity' => 123.4567,
        ]);

        $this->assertEquals('123.4567', $record->quantity);
    }

    public function test_usage_record_metadata_cast_to_array(): void
    {
        $user = User::factory()->create();
        $dimension = MeteringDimension::factory()->create();
        $metadata = ['source' => 'api', 'request_id' => '12345'];
        $record = UsageRecord::factory()->create([
            'user_id' => $user->id,
            'dimension_id' => $dimension->id,
            'metadata' => $metadata,
        ]);

        $this->assertIsArray($record->metadata);
        $this->assertEquals($metadata, $record->metadata);
    }

    public function test_usage_record_recorded_at_cast_to_datetime(): void
    {
        $user = User::factory()->create();
        $dimension = MeteringDimension::factory()->create();
        $record = UsageRecord::factory()->create([
            'user_id' => $user->id,
            'dimension_id' => $dimension->id,
            'recorded_at' => '2024-01-15 10:30:00',
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $record->recorded_at);
    }

    public function test_usage_record_filter_by_date_range(): void
    {
        $user = User::factory()->create();
        $dimension = MeteringDimension::factory()->create();

        UsageRecord::factory()->create([
            'user_id' => $user->id,
            'dimension_id' => $dimension->id,
            'recorded_at' => now()->subMonth(),
        ]);
        UsageRecord::factory()->create([
            'user_id' => $user->id,
            'dimension_id' => $dimension->id,
            'recorded_at' => now(),
        ]);
        UsageRecord::factory()->create([
            'user_id' => $user->id,
            'dimension_id' => $dimension->id,
            'recorded_at' => now()->addMonth(),
        ]);

        $records = UsageRecord::whereBetween('recorded_at', [
            now()->subWeek(),
            now()->addWeek()
        ])->get();

        $this->assertCount(1, $records);
    }

    public function test_usage_record_billing_period_dates_cast(): void
    {
        $user = User::factory()->create();
        $dimension = MeteringDimension::factory()->create();
        $record = UsageRecord::factory()->create([
            'user_id' => $user->id,
            'dimension_id' => $dimension->id,
            'billing_period_start' => '2024-01-01',
            'billing_period_end' => '2024-01-31',
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $record->billing_period_start);
        $this->assertInstanceOf(\Carbon\Carbon::class, $record->billing_period_end);
    }
}
