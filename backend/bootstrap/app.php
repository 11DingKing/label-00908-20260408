<?php

use App\Modules\Billing\Exceptions\BillingException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 使用 JWT 认证，不需要 Sanctum
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 统一 API 异常响应格式
        $exceptions->render(function (Throwable $e, Request $request) {
            if (!$request->expectsJson() && !$request->is('api/*')) {
                return null; // 非 API 请求使用默认处理
            }

            // 业务异常（BillingException 及其子类）
            if ($e instanceof BillingException) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'error_code' => $e->getErrorCode(),
                ], $e->getHttpStatus());
            }

            // 认证异常
            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'success' => false,
                    'message' => '未认证，请先登录',
                    'error_code' => 'UNAUTHENTICATED',
                ], 401);
            }

            // 验证异常
            if ($e instanceof ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => '请求参数验证失败',
                    'error_code' => 'VALIDATION_ERROR',
                    'errors' => $e->errors(),
                ], 422);
            }

            // 模型未找到
            if ($e instanceof ModelNotFoundException) {
                $model = class_basename($e->getModel());
                return response()->json([
                    'success' => false,
                    'message' => "资源不存在: {$model}",
                    'error_code' => 'RESOURCE_NOT_FOUND',
                ], 404);
            }

            // 404 路由未找到
            if ($e instanceof NotFoundHttpException) {
                return response()->json([
                    'success' => false,
                    'message' => '请求的接口不存在',
                    'error_code' => 'ENDPOINT_NOT_FOUND',
                ], 404);
            }

            // 其他 HTTP 异常
            if ($e instanceof HttpException) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: '请求错误',
                    'error_code' => 'HTTP_ERROR',
                ], $e->getStatusCode());
            }

            // 生产环境隐藏详细错误
            if (app()->isProduction()) {
                return response()->json([
                    'success' => false,
                    'message' => '服务器内部错误',
                    'error_code' => 'INTERNAL_ERROR',
                ], 500);
            }

            // 开发环境返回详细错误
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'INTERNAL_ERROR',
                'exception' => get_class($e),
                'trace' => collect($e->getTrace())->take(5)->toArray(),
            ], 500);
        });
    })->create();

return $app;
