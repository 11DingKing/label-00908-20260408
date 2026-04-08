<?php

namespace App\Modules\Subscription\Exceptions;

use App\Modules\Billing\Exceptions\BillingException;

class SubscriptionExpiredException extends BillingException
{
    public function __construct(string $message = '订阅已过期', ?\Throwable $previous = null)
    {
        parent::__construct($message, 'SUBSCRIPTION_EXPIRED', 403, $previous);
    }
}
