<?php

namespace App\Modules\Payment\Exceptions;

use App\Modules\Billing\Exceptions\BillingException;

class DuplicatePaymentException extends BillingException
{
    public function __construct(string $idempotencyKey, ?\Throwable $previous = null)
    {
        parent::__construct("重复支付请求: idempotency_key={$idempotencyKey}", 'DUPLICATE_PAYMENT', 409, $previous);
    }
}
