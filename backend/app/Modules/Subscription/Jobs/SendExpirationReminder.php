<?php

namespace App\Modules\Subscription\Jobs;

use App\Modules\Subscription\Events\SubscriptionExpiring;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendExpirationReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Subscription $subscription) {}

    public function handle(): void
    {
        if (!$this->subscription->isActive()) {
            return;
        }
        event(new SubscriptionExpiring($this->subscription));
        Log::channel('billing')->info('发送订阅到期提醒', [
            'subscription_id' => $this->subscription->id,
            'user_id' => $this->subscription->user_id,
            'end_date' => $this->subscription->end_date,
        ]);
    }
}
