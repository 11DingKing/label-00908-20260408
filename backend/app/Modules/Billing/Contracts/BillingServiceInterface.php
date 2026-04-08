<?php

namespace App\Modules\Billing\Contracts;

use App\Models\Bill;
use App\Models\Subscription;
use Carbon\Carbon;

interface BillingServiceInterface
{
    public function calculateBill(
        Subscription $subscription, Carbon $periodStart, Carbon $periodEnd,
        ?string $couponCode = null, ?string $currency = null
    ): Bill;

    public function calculateProration(Subscription $subscription, $newPlan): array;
}
