<?php

namespace App\Exceptions;

use App\Modules\Billing\Exceptions\BillingException;
use App\Modules\Billing\Exceptions\InvalidCouponException;
use App\Modules\Payment\Exceptions\PaymentGatewayException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;
use Illuminate\Support\Facades\Log;

class Handler extends ExceptionHandler
{
    protected $dontReport = [
        InvalidCouponException::class,
    ];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        // 计费业务异常 — 记录到专用通道
        $this->reportable(function (BillingException $e) {
            Log::channel('billing')->error($e->getErrorCode() . ': ' . $e->getMessage(), [
                'error_code' => $e->getErrorCode(),
                'http_status' => $e->getHttpStatus(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false; // 阻止默认报告
        });

        // 支付网关异常 — 额外记录网关名
        $this->reportable(function (PaymentGatewayException $e) {
            Log::channel('payment')->critical('支付网关异常', [
                'gateway' => $e->getGatewayName(),
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ]);
            return false;
        });
    }

    public function render($request, Throwable $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->handleApiException($request, $exception);
        }

        return parent::render($request, $exception);
    }

    protected function handleApiException($request, Throwable $exception)
    {
        // 自定义计费业务异常 — 直接由异常自身渲染
        if ($exception instanceof BillingException) {
            return $exception->render($request);
        }

        if ($exception instanceof ValidationException) {
            return response()->json([
                'message' => '数据验证失败',
                'error_code' => 'VALIDATION_ERROR',
                'errors' => $exception->errors(),
            ], 422);
        }

        if ($exception instanceof NotFoundHttpException) {
            return response()->json([
                'message' => '资源不存在',
                'error_code' => 'NOT_FOUND',
            ], 404);
        }

        if ($exception instanceof UnauthorizedHttpException) {
            return response()->json([
                'message' => '未授权访问',
                'error_code' => 'UNAUTHORIZED',
            ], 401);
        }

        if ($exception instanceof AccessDeniedHttpException) {
            return response()->json([
                'message' => '权限不足',
                'error_code' => 'FORBIDDEN',
            ], 403);
        }

        if ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json([
                'message' => '资源不存在',
                'error_code' => 'NOT_FOUND',
            ], 404);
        }

        Log::error('API异常', [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        return response()->json([
            'message' => config('app.debug') ? $exception->getMessage() : '服务器内部错误',
            'error_code' => 'INTERNAL_ERROR',
            'error' => config('app.debug') ? [
                'type' => get_class($exception),
                'trace' => $exception->getTraceAsString(),
            ] : null,
        ], 500);
    }
}
