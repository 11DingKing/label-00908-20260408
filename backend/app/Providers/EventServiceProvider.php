<?php

namespace App\Providers;

use App\Modules\Billing\Events\BillCreated;
use App\Modules\Billing\Listeners\SendBillNotification;
use App\Modules\Payment\Events\PaymentCompleted;
use App\Modules\Payment\Listeners\SendPaymentNotification;
use App\Modules\Subscription\Events\SubscriptionExpiring;
use App\Modules\Subscription\Listeners\HandleSubscriptionExpiring;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        PaymentCompleted::class => [
            SendPaymentNotification::class,
        ],
        BillCreated::class => [
            SendBillNotification::class,
        ],
        SubscriptionExpiring::class => [
            HandleSubscriptionExpiring::class,
        ],
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
