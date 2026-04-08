<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => '未授权访问', 'error_code' => 'UNAUTHORIZED'], 401);
        }

        // 超级管理员跳过角色检查
        if ($user->role === 'admin') {
            return $next($request);
        }

        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => '角色权限不足',
            'error_code' => 'FORBIDDEN',
        ], 403);
    }
}
