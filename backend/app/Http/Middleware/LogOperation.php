<?php

namespace App\Http\Middleware;

use App\Models\OperationLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogOperation
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 只记录增删改操作
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            OperationLog::create([
                'user_id' => $request->user()?->id,
                'action' => $request->method() . ' ' . $request->path(),
                'description' => $this->getActionDescription($request),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_data' => $this->sanitizeRequestData($request->all()),
            ]);
        }

        return $response;
    }

    protected function getActionDescription(Request $request): string
    {
        $path = $request->path();
        $method = $request->method();

        return match (true) {
            str_contains($path, 'subscriptions') && $method === 'POST' => '创建订阅',
            str_contains($path, 'subscriptions') && $method === 'DELETE' => '取消订阅',
            str_contains($path, 'usage') && $method === 'POST' => '记录使用量',
            str_contains($path, 'payments') && $method === 'POST' => '创建支付',
            str_contains($path, 'bills') && $method === 'POST' => '生成账单',
            default => $method . ' ' . $path,
        };
    }

    protected function sanitizeRequestData(array $data): array
    {
        // 移除敏感信息
        unset($data['password'], $data['password_confirmation']);
        return $data;
    }
}
