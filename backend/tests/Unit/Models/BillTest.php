<?php

namespace Tests\Unit\Models;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Modules\Billing\Models\Bill as ModuleBill;
use App\Modules\Billing\Models\BillItem as ModuleBillItem;
use App\Modules\Payment\Models\Payment as ModulePayment;
use App\Modules\Subscription\Models\Subscription as ModuleSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillTest extends TestCase
{
    use RefreshDatabase;

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
        ], $attributes));
    }

    public function test_bill_belongs_to_user(): void
    {
        $bill = $this->createBill();

        $this->assertInstanceOf(User::class, $bill->user);
    }

    public function test_bill_belongs_to_subscription(): void
    {
        $bill = $this->createBill();

        $this->assertInstanceOf(ModuleSubscription::class, $bill->subscription);
    }

    public function test_bill_has_items_relationship(): void
    {
        $bill = $this->createBill();
        BillItem::factory()->count(3)->create(['bill_id' => $bill->id]);

        $this->assertCount(3, $bill->items);
        $this->assertInstanceOf(ModuleBillItem::class, $bill->items->first());
    }

    public function test_bill_has_payments_relationship(): void
    {
        $bill = $this->createBill();
        Payment::factory()->count(2)->create([
            'user_id' => $bill->user_id,
            'bill_id' => $bill->id,
        ]);

        $this->assertCount(2, $bill->payments);
        $this->assertInstanceOf(ModulePayment::class, $bill->payments->first());
    }

    public function test_bill_is_paid(): void
    {
        $paidBill = $this->createBill(['status' => 'paid']);
        $pendingBill = $this->createBill(['status' => 'pending']);

        $this->assertTrue($paidBill->isPaid());
        $this->assertFalse($pendingBill->isPaid());
    }

    public function test_bill_is_pending(): void
    {
        $pendingBill = $this->createBill(['status' => 'pending']);
        $paidBill = $this->createBill(['status' => 'paid']);

        $this->assertTrue($pendingBill->isPending());
        $this->assertFalse($paidBill->isPending());
    }

    public function test_bill_is_overdue(): void
    {
        $overdueBill = $this->createBill([
            'status' => 'pending',
            'due_date' => now()->subDays(5),
        ]);
        $notOverdueBill = $this->createBill([
            'status' => 'pending',
            'due_date' => now()->addDays(5),
        ]);

        $this->assertTrue($overdueBill->isOverdue());
        $this->assertFalse($notOverdueBill->isOverdue());
    }

    public function test_bill_mark_as_paid(): void
    {
        $bill = $this->createBill(['status' => 'pending']);

        $bill->markAsPaid();

        $this->assertEquals('paid', $bill->status);
        $this->assertNotNull($bill->paid_at);
    }

    public function test_bill_mark_as_overdue(): void
    {
        $bill = $this->createBill(['status' => 'pending']);

        $bill->markAsOverdue();

        $this->assertEquals('overdue', $bill->status);
    }

    public function test_bill_cancel(): void
    {
        $bill = $this->createBill(['status' => 'pending']);

        $bill->cancel();

        $this->assertEquals('cancelled', $bill->status);
    }

    public function test_bill_calculate_total(): void
    {
        $bill = $this->createBill([
            'subscription_fee' => 100.00,
            'usage_fee' => 50.00,
            'discount' => 10.00,
            'tax' => 5.00,
        ]);

        $total = $bill->calculateTotal();

        $this->assertEquals(145.00, $total);
    }

    public function test_bill_get_outstanding_amount(): void
    {
        $bill = $this->createBill(['total_amount' => 100.00]);
        
        Payment::factory()->create([
            'user_id' => $bill->user_id,
            'bill_id' => $bill->id,
            'amount' => 30.00,
            'status' => 'completed',
        ]);

        $outstanding = $bill->getOutstandingAmount();

        $this->assertEquals(70.00, $outstanding);
    }

    public function test_bill_pending_scope(): void
    {
        $this->createBill(['status' => 'pending']);
        $this->createBill(['status' => 'pending']);
        $this->createBill(['status' => 'paid']);

        $pendingBills = Bill::pending()->get();

        $this->assertCount(2, $pendingBills);
    }

    public function test_bill_paid_scope(): void
    {
        $this->createBill(['status' => 'paid']);
        $this->createBill(['status' => 'paid']);
        $this->createBill(['status' => 'pending']);

        $paidBills = Bill::paid()->get();

        $this->assertCount(2, $paidBills);
    }

    public function test_bill_amounts_cast_to_decimal(): void
    {
        $bill = $this->createBill([
            'subscription_fee' => 99.99,
            'usage_fee' => 49.99,
            'total_amount' => 149.98,
        ]);

        $this->assertEquals('99.99', $bill->subscription_fee);
        $this->assertEquals('49.99', $bill->usage_fee);
        $this->assertEquals('149.98', $bill->total_amount);
    }
}
