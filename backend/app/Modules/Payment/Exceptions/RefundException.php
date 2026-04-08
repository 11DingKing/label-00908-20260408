<?php

namespace App\Modules\Payment\Exceptions;

use App\Modules\Billing\Exceptions\BillingException;

class RefundException extends BillingException
{
    public function __construct(string $message = '退款处理失败', ?\Throwable $previous = null)
    {
        parent::__construct($message, 'REFUND_FAILED', 422, $previous);
    }
}
