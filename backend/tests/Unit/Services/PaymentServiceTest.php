<?php

namespace Tests\Unit\Services;

use App\Modules\Payment\Contracts\PaymentGatewayInterface;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Modules\Payment\Services\PaymentGatewayManager;
use App\Modules\Payment\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the gateway manager to avoid real API calls
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
        $this->paymentService = app(PaymentService::class);
    }

    private function createBill(array $attributes = []): Bill
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        return Bill::factory()->create(array_merge([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'status' => 'pending',
            'total_amount' => 100.00,
        ], $attributes));
    }

    public function test_create_payment_creates_payment_record(): void
    {
        $bill = $this->createBill();

        $result = $this->paymentService->createPayment($bill, 'alipay');

        $payment = $result['payment'];
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals($bill->user_id, $payment->user_id);
        $this->assertEquals($bill->id, $payment->bill_id);
        $this->assertEquals('alipay', $payment->payment_method);
        $this->assertEquals($bill->total_amount, $payment->amount);
    }

    public function test_create_payment_with_payment_data(): void
    {
        $bill = $this->createBill();
        $paymentData = ['order_id' => '12345', 'channel' => 'app'];

        $result = $this->paymentService->createPayment($bill, 'wechat', ['payment_data' => $paymentData]);

        $payment = $result['payment'];
        $this->assertEquals($paymentData, $payment->payment_data);
    }

    public function test_create_payment_different_methods(): void
    {
        $bill1 = $this->createBill();
        $bill2 = $this->createBill();
        $bill3 = $this->createBill();

        $r1 = $this->paymentService->createPayment($bill1, 'alipay');
        $r2 = $this->paymentService->createPayment($bill2, 'wechat');
        $r3 = $this->paymentService->createPayment($bill3, 'bank_transfer');

        $this->assertEquals('alipay', $r1['payment']->payment_method);
        $this->assertEquals('wechat', $r2['payment']->payment_method);
        $this->assertEquals('bank_transfer', $r3['payment']->payment_method);
    }

    public function test_process_payment_callback_success(): void
    {
        $bill = $this->createBill(['status' => 'pending']);
        $payment = Payment::factory()->create([
            'user_id' => $bill->user_id,
            'bill_id' => $bill->id,
            'status' => 'pending',
        ]);

        $this->paymentService->processPaymentCallback($payment, 'txn_123456', true);

        $payment->refresh();
        $bill->refresh();

        $this->assertEquals('completed', $payment->status);
        $this->assertEquals('txn_123456', $payment->transaction_id);
        $this->assertNotNull($payment->paid_at);
        $this->assertEquals('paid', $bill->status);
    }

    public function test_process_payment_callback_failure(): void
    {
        $bill = $this->createBill(['status' => 'pending']);
        $payment = Payment::factory()->create([
            'user_id' => $bill->user_id,
            'bill_id' => $bill->id,
            'status' => 'pending',
        ]);

        $this->paymentService->processPaymentCallback($payment, 'txn_123456', false);

        $payment->refresh();
        $bill->refresh();

        $this->assertEquals('failed', $payment->status);
        $this->assertEquals('pending', $bill->status);
    }

    public function test_process_payment_callback_sets_transaction_id(): void
    {
        $bill = $this->createBill();
        $payment = Payment::factory()->create([
            'user_id' => $bill->user_id,
            'bill_id' => $bill->id,
            'status' => 'pending',
        ]);

        $this->paymentService->processPaymentCallback($payment, 'unique_txn_id', true);

        $payment->refresh();
        $this->assertEquals('unique_txn_id', $payment->transaction_id);
    }

    public function test_process_payment_callback_marks_bill_as_paid(): void
    {
        $bill = $this->createBill(['status' => 'pending']);
        $payment = Payment::factory()->create([
            'user_id' => $bill->user_id,
            'bill_id' => $bill->id,
            'status' => 'pending',
        ]);

        $this->paymentService->processPaymentCallback($payment, 'txn_123', true);

        $bill->refresh();
        $this->assertTrue($bill->isPaid());
        $this->assertNotNull($bill->paid_at);
    }
}
