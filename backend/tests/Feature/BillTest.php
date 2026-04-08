<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Bill;
use App\Models\BillItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);
    }

    public function test_user_can_get_their_bills(): void
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
            ->getJson('/api/bills');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(3, count($response->json('data.data')));
    }

    public function test_user_can_get_single_bill(): void
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

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson("/api/bills/{$bill->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $bill->id,
                    'bill_number' => $bill->bill_number,
                ],
            ]);
    }

    public function test_user_cannot_get_other_users_bill(): void
    {
        $otherUser = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $otherUser->id,
            'plan_id' => $plan->id,
        ]);

        $bill = Bill::factory()->create([
            'user_id' => $otherUser->id,
            'subscription_id' => $subscription->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson("/api/bills/{$bill->id}");

        $response->assertStatus(403);
    }

    public function test_user_can_get_bill_items(): void
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

        BillItem::factory()->count(3)->create([
            'bill_id' => $bill->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson("/api/bills/{$bill->id}/items");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_bill_has_correct_status(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
        ]);

        $bill = Bill::factory()->pending()->create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
        ]);

        $this->assertFalse($bill->isPaid());
        $this->assertFalse($bill->isOverdue());

        $bill->markAsPaid();
        $this->assertTrue($bill->isPaid());
        $this->assertNotNull($bill->paid_at);
    }

    public function test_bill_is_overdue_when_due_date_passed(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
        ]);

        $bill = Bill::factory()->create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'status' => 'pending',
            'due_date' => now()->subDay(),
        ]);

        $this->assertTrue($bill->isOverdue());
    }
}
