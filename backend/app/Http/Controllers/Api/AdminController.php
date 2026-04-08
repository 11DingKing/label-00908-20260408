<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\MeteringDimension;
use App\Models\Refund;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\Subscription;
use App\Models\Bill;
use App\Models\TaxRate;
use App\Models\User;
use App\Modules\Subscription\Requests\CreatePlanRequest;
use App\Modules\Subscription\Requests\CreateDimensionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    // ==================== 用户管理 ====================

    public function getUsers(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'users.view');

        $users = User::with(['activeSubscription.plan', 'roles'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));
        return response()->json(['data' => $users]);
    }

    // ==================== 订阅管理 ====================

    public function getSubscriptions(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'subscriptions.view');

        $subscriptions = Subscription::with(['user', 'plan'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));
        return response()->json(['data' => $subscriptions]);
    }

    // ==================== 账单管理 ====================

    public function getBills(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'bills.view');

        $bills = Bill::with(['user', 'subscription.plan'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));
        return response()->json(['data' => $bills]);
    }

    // ==================== 订阅计划管理 ====================

    public function getPlans(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'plans.view');

        $plans = SubscriptionPlan::orderBy('sort_order')->get();
        return response()->json(['data' => $plans]);
    }

    public function createPlan(CreatePlanRequest $request): JsonResponse
    {
        $this->authorizePermission($request, 'plans.create');

        $plan = SubscriptionPlan::create($request->validated());
        return response()->json(['message' => '订阅计划创建成功', 'data' => $plan], 201);
    }

    public function updatePlan(Request $request, $id): JsonResponse
    {
        $this->authorizePermission($request, 'plans.update');

        $plan = SubscriptionPlan::findOrFail($id);
        $plan->update($request->only([
            'name', 'description', 'price', 'billing_cycle', 'currency', 'tax_rate',
            'features', 'included_usage', 'usage_pricing', 'tiered_pricing', 'is_active', 'sort_order',
        ]));
        return response()->json(['message' => '订阅计划更新成功', 'data' => $plan]);
    }

    // ==================== 计量维度管理 ====================

    public function getDimensions(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'dimensions.view');

        $dimensions = MeteringDimension::orderBy('created_at', 'desc')->get();
        return response()->json(['data' => $dimensions]);
    }

    public function createDimension(CreateDimensionRequest $request): JsonResponse
    {
        $this->authorizePermission($request, 'dimensions.create');

        $dimension = MeteringDimension::create($request->validated());
        return response()->json(['message' => '计量维度创建成功', 'data' => $dimension], 201);
    }

    // ==================== 优惠券管理 ====================

    public function getCoupons(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'coupons.view');

        $coupons = Coupon::orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));
        return response()->json(['data' => $coupons]);
    }

    public function createCoupon(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'coupons.create');

        $request->validate([
            'code' => 'required|string|unique:coupons,code|max:50',
            'name' => 'required|string|max:255',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0.01',
            'min_amount' => 'numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'valid_from' => 'required|date',
            'valid_until' => 'required|date|after:valid_from',
        ]);

        $coupon = Coupon::create($request->all());
        return response()->json(['message' => '优惠券创建成功', 'data' => $coupon], 201);
    }

    public function updateCoupon(Request $request, $id): JsonResponse
    {
        $this->authorizePermission($request, 'coupons.update');

        $coupon = Coupon::findOrFail($id);
        $coupon->update($request->only([
            'name', 'type', 'value', 'min_amount', 'max_discount',
            'max_uses', 'valid_from', 'valid_until', 'is_active',
        ]));
        return response()->json(['message' => '优惠券更新成功', 'data' => $coupon]);
    }

    // ==================== 税率管理 ====================

    public function getTaxRates(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'tax_rates.view');

        $rates = TaxRate::orderBy('region')->get();
        return response()->json(['data' => $rates]);
    }

    public function createTaxRate(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'tax_rates.create');

        $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|unique:tax_rates,code|max:50',
            'rate' => 'required|numeric|min:0|max:1',
            'region' => 'required|string|max:50',
            'is_inclusive' => 'boolean',
        ]);

        $rate = TaxRate::create($request->all());
        return response()->json(['message' => '税率创建成功', 'data' => $rate], 201);
    }

    public function updateTaxRate(Request $request, $id): JsonResponse
    {
        $this->authorizePermission($request, 'tax_rates.update');

        $rate = TaxRate::findOrFail($id);
        $rate->update($request->only(['name', 'rate', 'region', 'is_inclusive', 'is_active']));
        return response()->json(['message' => '税率更新成功', 'data' => $rate]);
    }

    // ==================== 退款管理 ====================

    public function getRefunds(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'refunds.view');

        $refunds = Refund::with(['payment', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));
        return response()->json(['data' => $refunds]);
    }

    public function processRefund(Request $request, $id): JsonResponse
    {
        $this->authorizePermission($request, 'refunds.process');

        $refund = Refund::findOrFail($id);

        $request->validate([
            'action' => 'required|in:approve,reject',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($request->action === 'approve') {
            $refund->markAsCompleted();
            $refund->update(['notes' => $request->notes]);
        } else {
            $refund->update(['status' => 'failed', 'notes' => $request->notes ?? '退款被拒绝']);
        }

        return response()->json(['message' => '退款处理完成', 'data' => $refund->fresh()]);
    }

    // ==================== 角色管理 ====================

    public function getRoles(Request $request): JsonResponse
    {
        $this->authorizePermission($request, 'roles.view');

        $roles = Role::with('permissions')->get();
        return response()->json(['data' => $roles]);
    }

    public function assignRoles(Request $request, $id): JsonResponse
    {
        $this->authorizePermission($request, 'roles.assign');

        $user = User::findOrFail($id);

        $request->validate([
            'role_ids' => 'required|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $user->roles()->sync($request->role_ids);

        return response()->json([
            'message' => '角色分配成功',
            'data' => $user->load('roles'),
        ]);
    }

    // ==================== 权限检查 ====================

    /**
     * 细粒度权限检查
     * 
     * 超级管理员自动放行，其他角色需要具备对应权限
     */
    protected function authorizePermission(Request $request, string $permission): void
    {
        $user = $request->user();

        if (!$user) {
            abort(401, '未认证');
        }

        // 超级管理员跳过权限检查
        $superAdminRoles = config('auth.super_admin_roles', \App\Constants\RoleConstants::getSuperAdminRoles());
        if (in_array($user->role, $superAdminRoles, true)) {
            return;
        }

        // 检查具体权限
        if (!$user->hasPermission($permission)) {
            abort(403, "权限不足，需要权限: {$permission}");
        }
    }
}
