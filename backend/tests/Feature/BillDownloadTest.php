<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillDownloadTest extends TestCase
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

    public function test_user_can_download_bill_pdf(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'plan_id' => $plan->id,
        ]);

        $bill = Bill::factory()->create([
            'user_id' => $this->user->id,
            'subscription_id' => $subscription->id,
            'bill_number' => 'BILL-20240101-000001',
        ]);

        BillItem::factory()->create([
            'bill_id' => $bill->id,
            'item_type' => 'subscription',
            'description' => '订阅费用',
            'amount' => 100.00,
        ]);

        // Create a mock that returns the correct type
        $pdfMock = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdfMock->shouldReceive('download')
            ->once()
            ->andReturn(new \Illuminate\Http\Response('fake-pdf-content', 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="bill-BILL-20240101-000001.pdf"',
            ]));

        Pdf::shouldReceive('loadView')
            ->once()
            ->with('bills.pdf', \Mockery::on(function ($data) use ($bill) {
                return isset($data['bill']) && $data['bill']->id === $bill->id;
            }))
            ->andReturn($pdfMock);

        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->postJson("/api/bills/{$bill->id}/download");

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_user_cannot_download_other_users_bill(): void
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
            ->postJson("/api/bills/{$bill->id}/download");

        $response->assertStatus(403);
    }

    public function test_download_nonexistent_bill_returns_404(): void
    {
        $response = $this->withHeader('Authorization', "Bearer $this->token")
            ->postJson('/api/bills/99999/download');

        $response->assertStatus(404);
    }
}
