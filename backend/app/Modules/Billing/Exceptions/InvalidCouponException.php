<?php

namespace App\Modules\Billing\Exceptions;

class InvalidCouponException extends BillingException
{
    public function __construct(string $message = '优惠券无效', ?\Throwable $previous = null)
    {
        parent::__construct($message, 'INVALID_COUPON', 422, $previous);
    }
}
