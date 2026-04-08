<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\MeteringDimension;
use App\Models\UsageRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
        
        $this->plan = SubscriptionPlan::factory()->create();
        $this->subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
        ]);
        
        $this->dimension = MeteringDimension::factory()->create([
            'code' => 'api_calls',
            'name' => 'API调用次数',
            'unit' => '次',
            'unit_price' => 0.01,
        ]);
    }

    public function test_user_can_record_usage(): void
    {
        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->postJson('/api/usage/record', [
                'dimension_code' => $this->dimension->code,
                'quantity' => 100,
                'metadata' => ['source' => 'test'],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => '使用量记录成功',
            ]);

        $this->assertDatabaseHas('usage_records', [
            'user_id' => $this->user->id,
            'dimension_id' => $this->dimension->id,
            'quantity' => 100,
        ]);
    }

    public function test_user_can_record_usage_without_subscription(): void
    {
        $this->subscription->update(['status' => 'cancelled']);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->postJson('/api/usage/record', [
                'dimension_code' => $this->dimension->code,
                'quantity' => 100,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('usage_records', [
            'user_id' => $this->user->id,
            'subscription_id' => null,
        ]);
    }

    public function test_record_usage_requires_valid_dimension(): void
    {
        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->postJson('/api/usage/record', [
                'dimension_code' => 'invalid_dimension',
                'quantity' => 100,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dimension_code']);
    }

    public function test_record_usage_requires_positive_quantity(): void
    {
        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->postJson('/api/usage/record', [
                'dimension_code' => $this->dimension->code,
                'quantity' => -10,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['quantity']);
    }

    public function test_user_can_get_dimensions(): void
    {
        MeteringDimension::factory()->count(3)->create(['is_active' => true]);
        MeteringDimension::factory()->create(['is_active' => false]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson('/api/usage/dimensions');

        $response->assertStatus(200)
            ->assertJsonCount(4, 'data'); // 包括 setUp 中创建的
    }

    public function test_user_can_get_usage_records(): void
    {
        UsageRecord::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'dimension_id' => $this->dimension->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson('/api/usage/records');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(5, count($response->json('data.data')));
    }

    public function test_user_can_get_usage_statistics(): void
    {
        UsageRecord::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'dimension_id' => $this->dimension->id,
            'quantity' => 100,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson('/api/usage/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'dimension_code',
                        'dimension_name',
                        'unit',
                        'total_quantity',
                        'record_count',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals(300, $data[0]['total_quantity']);
        $this->assertEquals(3, $data[0]['record_count']);
    }

    public function test_usage_statistics_can_filter_by_date_range(): void
    {
        UsageRecord::factory()->create([
            'user_id' => $this->user->id,
            'dimension_id' => $this->dimension->id,
            'quantity' => 100,
            'recorded_at' => now()->subMonth(),
        ]);

        UsageRecord::factory()->create([
            'user_id' => $this->user->id,
            'dimension_id' => $this->dimension->id,
            'quantity' => 200,
            'recorded_at' => now(),
        ]);

        $startDate = now()->subWeek()->toDateString();
        $endDate = now()->toDateString();

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson("/api/usage/statistics?start_date={$startDate}&end_date={$endDate}");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        // 应该只包含最近一周的记录
        $this->assertEquals(200, $data[0]['total_quantity']);
    }
}
