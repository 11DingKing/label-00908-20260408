<?php

namespace App\Modules\Subscription\Observers;

use App\Models\Subscription;
use Illuminate\Support\Facades\Log;

class SubscriptionObserver
{
    public function created(Subscription $subscription): void
    {
        Log::channel('billing')->info('订阅已创建', [
            'subscription_id' => $subscription->id, 'user_id' => $subscription->user_id,
            'plan_id' => $subscription->plan_id, 'status' => $subscription->status,
        ]);
    }

    public function updated(Subscription $subscription): void
    {
        if ($subscription->wasChanged('status')) {
            Log::channel('billing')->info('订阅状态变更', [
                'subscription_id' => $subscription->id,
                'old_status' => $subscription->getOriginal('status'),
                'new_status' => $subscription->status,
            ]);
        }
    }

    public function deleted(Subscription $subscription): void
    {
        Log::channel('billing')->warning('订阅已删除', [
            'subscription_id' => $subscription->id, 'user_id' => $subscription->user_id,
        ]);
    }
}
