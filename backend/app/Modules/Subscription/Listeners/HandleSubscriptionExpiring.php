<?php

namespace App\Modules\Subscription\Listeners;

use App\Modules\Subscription\Events\SubscriptionExpiring;
use App\Modules\Subscription\Mail\SubscriptionExpiringNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class HandleSubscriptionExpiring implements ShouldQueue
{
    public function handle(SubscriptionExpiring $event): void
    {
        $subscription = $event->subscription;
        Log::channel('billing')->info('订阅即将到期通知', [
            'subscription_id' => $subscription->id,
            'user_id' => $subscription->user_id,
            'end_date' => $subscription->end_date,
        ]);
        Mail::to($subscription->user)->send(new SubscriptionExpiringNotification($subscription));
    }
}
