<?php

namespace App\Modules\Payment\Exceptions;

use App\Modules\Billing\Exceptions\BillingException;

class PaymentGatewayException extends BillingException
{
    protected ?string $gatewayName;

    public function __construct(string $gatewayName, string $message = '支付网关错误', ?\Throwable $previous = null)
    {
        $this->gatewayName = $gatewayName;
        parent::__construct("[{$gatewayName}] {$message}", 'GATEWAY_ERROR', 502, $previous);
    }

    public function getGatewayName(): ?string { return $this->gatewayName; }
}
