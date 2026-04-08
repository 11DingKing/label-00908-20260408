<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Coupon;
use App\Models\MeteringDimension;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\UsageRecord;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Billing\Services\CouponService;
use App\Modules\Billing\Services\CurrencyService;
use App\Modules\Billing\Services\TaxService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnhancedBillingTest extends TestCase
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

    // --- 税务计算测试 ---
    public function test_tax_calculation_exclusive(): void
    {
        TaxRate::create([
            'name' => '增值税', 'code' => 'vat_cn', 'rate' => 0.0600,
            'region' => 'CN', 'is_inclusive' => false, 'is_active' => true,
        ]);

        $taxService = app(TaxService::class);
        $result = $taxService->calculateTax(100.00, 'CN');

        $this->assertEquals(6.00, $result['tax_amount']);
        $this->assertEquals(0.0600, $result['tax_rate']);
    }

    public function test_tax_calculation_inclusive(): void
    {
        TaxRate::create([
            'name' => '含税增值税', 'code' => 'vat_inc', 'rate' => 0.0600,
            'region' => 'CN', 'is_inclusive' => true, 'is_active' => true,
        ]);

        $taxService = app(TaxService::class);
        $result = $taxService->calculateTax(106.00, 'CN');

        $this->assertEquals(6.00, $result['tax_amount']);
    }

    public function test_bill_with_tax(): void
    {
        TaxRate::create([
            'name' => '增值税', 'code' => 'vat_test', 'rate' => 0.1000,
            'region' => 'CN', 'is_inclusive' => false, 'is_active' => true,
        ]);

        $plan = SubscriptionPlan::factory()->create(['price' => 300, 'billing_cycle' => 'monthly']);
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id, 'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $billingService = app(BillingService::class);
        $bill = $billingService->calculateBill(
            $subscription, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()
        );

        $this->assertGreaterThan(0, (float) $bill->tax);
        $this->assertEquals(0.1000, (float) $bill->tax_rate);
        // total = subscription_fee + tax (no discount, no usage)
        $this->assertEquals(
            round((float) $bill->subscription_fee + (float) $bill->tax, 2),
            (float) $bill->total_amount
        );
    }

    // --- 优惠券测试 ---
    public function test_coupon_percentage_discount(): void
    {
        $coupon = Coupon::factory()->percentage(20)->create();
        $couponService = app(CouponService::class);

        $result = $couponService->applyCoupon($coupon->code, 200.00, $this->user);

        $this->assertEquals(40.00, $result['discount_amount']);
    }

    public function test_coupon_fixed_discount(): void
    {
        $coupon = Coupon::factory()->fixed(30)->create();
        $couponService = app(CouponService::class);

        $result = $couponService->applyCoupon($coupon->code, 200.00, $this->user);

        $this->assertEquals(30.00, $result['discount_amount']);
    }

    public function test_expired_coupon_rejected(): void
    {
        $coupon = Coupon::factory()->expired()->create();
        $couponService = app(CouponService::class);

        $this->expectException(\App\Modules\Billing\Exceptions\InvalidCouponException::class);
        $couponService->applyCoupon($coupon->code, 200.00, $this->user);
    }

    public function test_coupon_max_discount_cap(): void
    {
        $coupon = Coupon::factory()->percentage(50)->create(['max_discount' => 20.00]);
        $couponService = app(CouponService::class);

        $result = $couponService->applyCoupon($coupon->code, 200.00, $this->user);

        $this->assertEquals(20.00, $result['discount_amount']); // capped at 20
    }

    public function test_bill_with_coupon(): void
    {
        $coupon = Coupon::factory()->fixed(50)->create();
        $plan = SubscriptionPlan::factory()->create(['price' => 300, 'billing_cycle' => 'monthly']);
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id, 'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $billingService = app(BillingService::class);
        $bill = $billingService->calculateBill(
            $subscription, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth(),
            $coupon->code
        );

        $this->assertEquals(50.00, (float) $bill->discount);
        $this->assertEquals($coupon->code, $bill->coupon_code);
        // total = subscription_fee - discount
        $this->assertEquals(
            round((float) $bill->subscription_fee - 50.00, 2),
            (float) $bill->total_amount
        );
    }

    // --- 多币种测试 ---
    public function test_currency_conversion(): void
    {
        $currencyService = app(CurrencyService::class);

        $usdAmount = $currencyService->convertFromBase(725.00, 'USD');
        $this->assertEquals(100.00, $usdAmount);

        $cnyAmount = $currencyService->convertToBase(100.00, 'USD');
        $this->assertEquals(725.00, $cnyAmount);
    }

    public function test_supported_currencies(): void
    {
        $currencyService = app(CurrencyService::class);
        $currencies = $currencyService->getSupportedCurrencies();

        $this->assertContains('CNY', $currencies);
        $this->assertContains('USD', $currencies);
    }

    // --- 按比例计算测试 ---
    public function test_proration_calculation(): void
    {
        $currentPlan = SubscriptionPlan::factory()->create(['price' => 100]);
        $newPlan = SubscriptionPlan::factory()->create(['price' => 200]);

        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $currentPlan->id,
            'start_date' => now()->subDays(15),
            'end_date' => now()->addDays(15),
            'status' => 'active',
        ]);

        $billingService = app(BillingService::class);
        $proration = $billingService->calculateProration($subscription, $newPlan);

        $this->assertArrayHasKey('prorated_amount', $proration);
        $this->assertArrayHasKey('remaining_days', $proration);
        $this->assertArrayHasKey('current_credit', $proration);
        $this->assertArrayHasKey('new_charge', $proration);
        $this->assertGreaterThan(0, $proration['prorated_amount']);
    }

    public function test_proration_api_endpoint(): void
    {
        $currentPlan = SubscriptionPlan::factory()->create(['price' => 100]);
        $newPlan = SubscriptionPlan::factory()->create(['price' => 200]);

        Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $currentPlan->id,
            'start_date' => now()->subDays(15),
            'end_date' => now()->addDays(15),
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->postJson('/api/billing/proration', ['plan_id' => $newPlan->id]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['prorated_amount', 'remaining_days', 'current_credit', 'new_charge'],
            ]);
    }

    // --- 退款测试 ---
    public function test_user_can_request_refund(): void
    {
        // Mock gateway
        $mockGateway = \Mockery::mock(\App\Modules\Payment\Contracts\PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('getName')->andReturn('test');
        $mockGateway->shouldReceive('refund')->andReturn([
            'gateway_refund_id' => 'ref-123', 'status' => 'completed', 'amount' => 50.00,
        ]);
        $mockManager = \Mockery::mock(\App\Modules\Payment\Services\PaymentGatewayManager::class);
        $mockManager->shouldReceive('gateway')->andReturn($mockGateway);
        $this->app->instance(\App\Modules\Payment\Services\PaymentGatewayManager::class, $mockManager);

        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id, 'plan_id' => $plan->id,
        ]);
        $bill = Bill::factory()->create([
            'user_id' => $this->user->id, 'subscription_id' => $subscription->id,
        ]);
        $payment = Payment::factory()->completed()->create([
            'user_id' => $this->user->id, 'bill_id' => $bill->id,
            'amount' => 100.00, 'gateway' => 'test', 'gateway_payment_id' => 'gw-123',
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->postJson("/api/payments/{$payment->id}/refund", [
                'amount' => 50.00,
                'reason' => '服务不满意',
            ]);

        $response->assertStatus(201)
            ->assertJson(['message' => '退款申请已提交']);

        $this->assertDatabaseHas('refunds', [
            'payment_id' => $payment->id,
            'amount' => 50.00,
            'type' => 'partial',
        ]);
    }

    // --- 增强的计费规则接口测试 ---
    public function test_billing_rules_include_enhanced_fields(): void
    {
        TaxRate::create([
            'name' => '增值税', 'code' => 'vat_rules', 'rate' => 0.0600,
            'region' => 'CN', 'is_inclusive' => false, 'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson('/api/billing/rules');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'billing_cycles',
                    'supported_currencies',
                    'exchange_rates',
                    'plans',
                    'metering_dimensions',
                    'tax_rates',
                    'rules' => [
                        'proration',
                        'overdue_grace_period_days',
                        'usage_billing',
                        'tax_calculation',
                        'coupon_support',
                        'refund_policy',
                        'multi_currency',
                    ],
                ],
            ]);
    }

    // --- 报表增强测试 ---
    public function test_report_overview_includes_tax_and_discount(): void
    {
        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson('/api/reports/overview');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_bills', 'paid_amount', 'pending_amount', 'bill_count',
                    'total_tax', 'total_discount', 'refunded_amount',
                ],
            ]);
    }

    public function test_report_trend_endpoint(): void
    {
        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->getJson('/api/reports/trend?group_by=day');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['period', 'group_by', 'trend'],
            ]);
    }

    // --- 自定义异常测试 ---
    public function test_refund_on_non_completed_payment_throws_exception(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id, 'plan_id' => $plan->id,
        ]);
        $bill = Bill::factory()->create([
            'user_id' => $this->user->id, 'subscription_id' => $subscription->id,
        ]);
        $payment = Payment::factory()->pending()->create([
            'user_id' => $this->user->id, 'bill_id' => $bill->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->postJson("/api/payments/{$payment->id}/refund", [
                'amount' => 10.00,
                'reason' => '测试',
            ]);

        $response->assertStatus(422)
            ->assertJson(['error_code' => 'REFUND_FAILED']);
    }
}
