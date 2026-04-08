<?php

namespace Tests\Feature;

use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use App\Models\User;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Bill;
use App\Models\Payment;
use App\Modules\Payment\Services\PaymentGatewayManager;
use App\Modules\Payment\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = auth('api')->login($this->user);

        // Mock gateway to avoid real API calls
        $mockGateway = \Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('getName')->andReturn('mock');
        $mockGateway->shouldReceive('createCharge')->andReturn([
            'gateway_payment_id' => 'mock_gw_123',
            'status' => 'pending',
            'payment_url' => 'https://mock.pay/123',
        ]);

        $mockManager = \Mockery::mock(PaymentGatewayManager::class);
        $mockManager->shouldReceive('gateway')->andReturn($mockGateway);

        $this->app->instance(PaymentGatewayManager::class, $mockManager);
    }

    public function test_user_can_create_payment(): void
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
            'total_amount' => 100.00,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->postJson('/api/payments', [
                'bill_id' => $bill->id,
                'payment_method' => 'alipay',
                'payment_data' => ['order_id' => 'test123'],
            ]);

        $response->assertStatus(201)
            ->assertJson(['message' => '支付订单已创建']);

        $this->assertDatabaseHas('payments', [
            'user_id' => $this->user->id,
            'bill_id' => $bill->id,
            'payment_method' => 'alipay',
        ]);
    }

    public function test_user_can_get_their_payments(): void
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

        Payment::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'bill_id' => $bill->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson('/api/payments');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(3, count($response->json('data.data')));
    }

    public function test_user_can_get_single_payment(): void
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

        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'bill_id' => $bill->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson("/api/payments/{$payment->id}");

        $response->assertStatus(200)
            ->assertJson(['data' => ['id' => $payment->id]]);
    }

    public function test_user_cannot_get_other_users_payment(): void
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

        $payment = Payment::factory()->create([
            'user_id' => $otherUser->id,
            'bill_id' => $bill->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson("/api/payments/{$payment->id}");

        $response->assertStatus(403);
    }

    public function test_payment_service_can_process_callback(): void
    {
        $paymentService = app(PaymentService::class);

        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
        ]);

        $bill = Bill::factory()->create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'status' => 'pending',
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'bill_id' => $bill->id,
            'status' => 'pending',
        ]);

        $paymentService->processPaymentCallback($payment, 'transaction123', true);

        $payment->refresh();
        $this->assertEquals('completed', $payment->status);
        $this->assertEquals('transaction123', $payment->transaction_id);
        $this->assertNotNull($payment->paid_at);

        $bill->refresh();
        $this->assertEquals('paid', $bill->status);
    }

    public function test_payment_callback_handles_failure(): void
    {
        $paymentService = app(PaymentService::class);

        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
        ]);

        $bill = Bill::factory()->create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'status' => 'pending',
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'bill_id' => $bill->id,
            'status' => 'pending',
        ]);

        $paymentService->processPaymentCallback($payment, 'transaction123', false);

        $payment->refresh();
        $this->assertEquals('failed', $payment->status);
    }

    public function test_create_payment_requires_valid_bill(): void
    {
        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->postJson('/api/payments', [
                'bill_id' => 99999,
                'payment_method' => 'alipay',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bill_id']);
    }

    public function test_create_payment_requires_valid_payment_method(): void
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
            ->postJson('/api/payments', [
                'bill_id' => $bill->id,
                'payment_method' => 'invalid_method',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }
}
