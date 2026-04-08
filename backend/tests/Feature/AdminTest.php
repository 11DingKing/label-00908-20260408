<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\MeteringDimension;
use App\Models\Subscription;
use App\Models\Bill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->adminToken = JWTAuth::fromUser($this->admin);
        
        $this->user = User::factory()->create(['role' => 'user']);
        $this->userToken = JWTAuth::fromUser($this->user);
    }

    public function test_admin_can_get_users(): void
    {
        User::factory()->count(5)->create();

        $response = $this->withHeader('Authorization', "Bearer $this->adminToken")
            ->getJson('/api/admin/users');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(7, count($response->json('data.data'))); // 5 + admin + user
    }

    public function test_regular_user_cannot_access_admin_users(): void
    {
        $response = $this->withHeader('Authorization', "Bearer $this->userToken")
            ->getJson('/api/admin/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_get_subscriptions(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        Subscription::factory()->count(3)->create(['plan_id' => $plan->id]);

        $response = $this->withHeader('Authorization', "Bearer $this->adminToken")
            ->getJson('/api/admin/subscriptions');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(3, count($response->json('data.data')));
    }

    public function test_admin_can_get_bills(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create(['plan_id' => $plan->id]);
        Bill::factory()->count(3)->create(['subscription_id' => $subscription->id]);

        $response = $this->withHeader('Authorization', "Bearer $this->adminToken")
            ->getJson('/api/admin/bills');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(3, count($response->json('data.data')));
    }

    public function test_admin_can_get_plans(): void
    {
        SubscriptionPlan::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', "Bearer $this->adminToken")
            ->getJson('/api/admin/plans');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_admin_can_create_subscription_plan(): void
    {
        $response = $this->withHeader('Authorization', "Bearer $this->adminToken")
            ->postJson('/api/admin/plans', [
                'name' => '测试计划',
                'code' => 'test_plan',
                'description' => '这是一个测试计划',
                'price' => 99.00,
                'billing_cycle' => 'monthly',
                'features' => ['feature1', 'feature2'],
                'included_usage' => ['api_calls' => 1000],
                'is_active' => true,
                'sort_order' => 1,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => '订阅计划创建成功',
            ]);

        $this->assertDatabaseHas('subscription_plans', [
            'code' => 'test_plan',
            'name' => '测试计划',
        ]);
    }

    public function test_admin_can_update_subscription_plan(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'name' => '原始名称',
            'price' => 50.00,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->adminToken")
            ->putJson("/api/admin/plans/{$plan->id}", [
                'name' => '更新后的名称',
                'price' => 100.00,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => '订阅计划更新成功',
            ]);

        $plan->refresh();
        $this->assertEquals('更新后的名称', $plan->name);
        $this->assertEquals(100.00, (float) $plan->price);
    }

    public function test_admin_can_get_metering_dimensions(): void
    {
        MeteringDimension::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', "Bearer $this->adminToken")
            ->getJson('/api/admin/metering-dimensions');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_admin_can_create_metering_dimension(): void
    {
        $response = $this->withHeader('Authorization', "Bearer $this->adminToken")
            ->postJson('/api/admin/metering-dimensions', [
                'code' => 'test_dimension',
                'name' => '测试维度',
                'description' => '这是一个测试维度',
                'unit' => '次',
                'unit_price' => 0.01,
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => '计量维度创建成功',
            ]);

        $this->assertDatabaseHas('metering_dimensions', [
            'code' => 'test_dimension',
            'name' => '测试维度',
        ]);
    }

    public function test_create_plan_requires_unique_code(): void
    {
        $existingPlan = SubscriptionPlan::factory()->create(['code' => 'existing_code']);

        $response = $this->withHeader('Authorization', "Bearer $this->adminToken")
            ->postJson('/api/admin/plans', [
                'name' => '测试计划',
                'code' => 'existing_code',
                'price' => 99.00,
                'billing_cycle' => 'monthly',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_create_dimension_requires_unique_code(): void
    {
        $existingDimension = MeteringDimension::factory()->create(['code' => 'existing_code']);

        $response = $this->withHeader('Authorization', "Bearer $this->adminToken")
            ->postJson('/api/admin/metering-dimensions', [
                'code' => 'existing_code',
                'name' => '测试维度',
                'unit' => '次',
                'unit_price' => 0.01,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }
}
