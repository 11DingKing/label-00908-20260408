<?php

namespace Tests\Unit\Models;

use App\Models\Payment;
use App\Models\Bill;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Modules\Billing\Models\Bill as ModuleBill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function createPayment(array $attributes = []): Payment
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
        ]);
        $bill = Bill::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
        ]);

        return Payment::factory()->create(array_merge([
            'user_id' => $user->id,
            'bill_id' => $bill->id,
        ], $attributes));
    }

    public function test_payment_belongs_to_user(): void
    {
        $payment = $this->createPayment();

        $this->assertInstanceOf(User::class, $payment->user);
    }

    public function test_payment_belongs_to_bill(): void
    {
        $payment = $this->createPayment();

        $this->assertInstanceOf(ModuleBill::class, $payment->bill);
    }

    public function test_payment_is_completed(): void
    {
        $completedPayment = $this->createPayment(['status' => 'completed']);
        $pendingPayment = $this->createPayment(['status' => 'pending']);

        $this->assertTrue($completedPayment->isCompleted());
        $this->assertFalse($pendingPayment->isCompleted());
    }

    public function test_payment_status_check(): void
    {
        $pendingPayment = $this->createPayment(['status' => 'pending']);
        $completedPayment = $this->createPayment(['status' => 'completed']);
        $failedPayment = $this->createPayment(['status' => 'failed']);

        $this->assertEquals('pending', $pendingPayment->status);
        $this->assertEquals('completed', $completedPayment->status);
        $this->assertEquals('failed', $failedPayment->status);
    }

    public function test_payment_mark_as_completed(): void
    {
        $payment = $this->createPayment(['status' => 'pending']);

        $payment->markAsCompleted('txn_123456');

        $this->assertEquals('completed', $payment->status);
        $this->assertEquals('txn_123456', $payment->transaction_id);
        $this->assertNotNull($payment->paid_at);
    }

    public function test_payment_mark_as_completed_updates_bill(): void
    {
        $payment = $this->createPayment(['status' => 'pending']);
        $bill = $payment->bill;

        $payment->markAsCompleted('txn_123456');

        $bill->refresh();
        $this->assertEquals('paid', $bill->status);
    }

    public function test_payment_amount_cast_to_decimal(): void
    {
        $payment = $this->createPayment(['amount' => 99.99]);

        $this->assertEquals('99.99', $payment->amount);
    }

    public function test_payment_data_cast_to_array(): void
    {
        $paymentData = ['order_id' => '123', 'channel' => 'alipay'];
        $payment = $this->createPayment(['payment_data' => $paymentData]);

        $this->assertIsArray($payment->payment_data);
        $this->assertEquals($paymentData, $payment->payment_data);
    }

    public function test_payment_methods(): void
    {
        $alipayPayment = $this->createPayment(['payment_method' => 'alipay']);
        $wechatPayment = $this->createPayment(['payment_method' => 'wechat']);
        $bankPayment = $this->createPayment(['payment_method' => 'bank_transfer']);

        $this->assertEquals('alipay', $alipayPayment->payment_method);
        $this->assertEquals('wechat', $wechatPayment->payment_method);
        $this->assertEquals('bank_transfer', $bankPayment->payment_method);
    }

    public function test_payment_paid_at_cast_to_datetime(): void
    {
        $payment = $this->createPayment([
            'status' => 'completed',
            'paid_at' => '2024-01-15 10:30:00',
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $payment->paid_at);
    }

    public function test_payment_refunded_at_cast_to_datetime(): void
    {
        $payment = $this->createPayment([
            'refunded_at' => '2024-01-20 14:00:00',
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $payment->refunded_at);
    }
}
