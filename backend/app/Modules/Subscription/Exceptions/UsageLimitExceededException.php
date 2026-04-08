<?php

namespace App\Modules\Subscription\Exceptions;

use App\Modules\Billing\Exceptions\BillingException;

class UsageLimitExceededException extends BillingException
{
    public function __construct(string $dimensionCode, float $limit, float $current, ?\Throwable $previous = null)
    {
        $message = "使用量超出限制: 维度[{$dimensionCode}] 限额={$limit}, 当前={$current}";
        parent::__construct($message, 'USAGE_LIMIT_EXCEEDED', 429, $previous);
    }
}
