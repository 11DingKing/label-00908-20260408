<?php

namespace App\Modules\Payment\Exceptions;

use App\Modules\Billing\Exceptions\BillingException;

class PaymentDeclinedException extends BillingException
{
    public function __construct(string $message = '支付被拒绝', ?string $gatewayMessage = null, ?\Throwable $previous = null)
    {
        $fullMessage = $gatewayMessage ? "{$message}: {$gatewayMessage}" : $message;
        parent::__construct($fullMessage, 'PAYMENT_DECLINED', 402, $previous);
    }
}
