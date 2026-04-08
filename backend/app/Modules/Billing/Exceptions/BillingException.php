<?php

namespace App\Modules\Billing\Exceptions;

use Exception;

class BillingException extends Exception
{
    protected string $errorCode;
    protected int $httpStatus;

    public function __construct(string $message, string $errorCode = 'BILLING_ERROR', int $httpStatus = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
        ], $this->httpStatus);
    }
}
