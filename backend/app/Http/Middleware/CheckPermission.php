<?php

namespace App\Http\Middleware;

use App\Constants\RoleConstants;
use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => '未授权访问', 'error_code' => 'UNAUTHORIZED'], 401);
        }

        // 超级管理员跳过权限检查（优先从配置读取，回退到常量定义）
        $superAdminRoles = config('auth.super_admin_roles', RoleConstants::getSuperAdminRoles());
        if (in_array($user->role, $superAdminRoles, true)) {
            return $next($request);
        }

        if (!$user->hasPermission($permission)) {
            return response()->json([
                'message' => '权限不足',
                'error_code' => 'FORBIDDEN',
                'required_permission' => $permission,
            ], 403);
        }

        return $next($request);
    }
}
