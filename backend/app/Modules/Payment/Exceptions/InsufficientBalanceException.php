<?php

namespace App\Modules\Payment\Exceptions;

use App\Modules\Billing\Exceptions\BillingException;

class InsufficientBalanceException extends BillingException
{
    public function __construct(float $required, float $available, ?\Throwable $previous = null)
    {
        $message = "余额不足: 需要={$required}, 可用={$available}";
        parent::__construct($message, 'INSUFFICIENT_BALANCE', 402, $previous);
    }
}
