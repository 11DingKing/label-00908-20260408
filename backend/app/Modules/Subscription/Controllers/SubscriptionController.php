<?php

namespace App\Modules\Subscription\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Subscription\Requests\CreateSubscriptionRequest;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Modules\Billing\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function __construct(protected BillingService $billingService) {}

    public function getPlans(Request $request): JsonResponse
    {
        $plans = SubscriptionPlan::active()->orderBy('sort_order')->orderBy('price')->get();
        return response()->json(['data' => $plans]);
    }

    public function getPlan($id): JsonResponse
    {
        return response()->json(['data' => SubscriptionPlan::findOrFail($id)]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscriptions = Subscription::where('user_id', $user->id)->with('plan')->orderBy('created_at', 'desc')->get();
        return response()->json(['data' => $subscriptions]);
    }

    public function store(CreateSubscriptionRequest $request): JsonResponse
    {
        $user = $request->user();
        $plan = SubscriptionPlan::findOrFail($request->plan_id);
        $subscription = DB::transaction(function () use ($user, $plan) {
            $user->subscriptions()->where('status', 'active')->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $startDate = now();
            $endDate = $this->calculateEndDate($startDate, $plan->billing_cycle);
            $subscription = Subscription::create([
                'user_id' => $user->id, 'plan_id' => $plan->id, 'status' => 'active',
                'start_date' => $startDate, 'end_date' => $endDate, 'auto_renew' => true,
            ]);
            Log::info('订阅已创建', ['subscription_id' => $subscription->id, 'user_id' => $user->id, 'plan_id' => $plan->id]);
            return $subscription;
        });
        return response()->json(['message' => '订阅创建成功', 'data' => $subscription->load('plan')], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $subscription = Subscription::with('plan')->findOrFail($id);
        $this->authorize('view', $subscription);
        return response()->json(['data' => $subscription]);
    }

    public function cancel(Request $request, $id): JsonResponse
    {
        $subscription = Subscription::where('status', 'active')->findOrFail($id);
        $this->authorize('cancel', $subscription);
        $subscription->cancel($request->input('reason'));
        Log::info('订阅已取消', ['subscription_id' => $subscription->id, 'user_id' => $request->user()->id]);
        return response()->json(['message' => '订阅已取消', 'data' => $subscription]);
    }

    public function upgrade(Request $request, $id): JsonResponse
    {
        $subscription = Subscription::where('status', 'active')->findOrFail($id);
        $this->authorize('upgrade', $subscription);
        $request->validate(['plan_id' => 'required|exists:subscription_plans,id']);
        $newPlan = SubscriptionPlan::findOrFail($request->plan_id);
        $currentPlan = $subscription->plan;
        if ($newPlan->price <= $currentPlan->price) {
            return response()->json(['message' => '升级计划的价格必须高于当前计划，如需降级请使用降级接口'], 422);
        }
        $user = $request->user();
        $subscription = DB::transaction(function () use ($subscription, $newPlan, $currentPlan, $user) {
            $remainingDays = now()->diffInDays($subscription->end_date);
            $totalDays = $subscription->start_date->diffInDays($subscription->end_date);
            $ratio = $totalDays > 0 ? $remainingDays / $totalDays : 0;
            $proratedDifference = round(round($newPlan->price * $ratio, 2) - round($currentPlan->price * $ratio, 2), 2);
            $subscription->update(['plan_id' => $newPlan->id]);
            Log::info('订阅已升级', ['subscription_id' => $subscription->id, 'user_id' => $user->id, 'old_plan_id' => $currentPlan->id, 'new_plan_id' => $newPlan->id, 'prorated_difference' => $proratedDifference]);
            return $subscription;
        });
        return response()->json(['message' => '订阅升级成功', 'data' => $subscription->load('plan')]);
    }

    public function downgrade(Request $request, $id): JsonResponse
    {
        $subscription = Subscription::where('status', 'active')->findOrFail($id);
        $this->authorize('downgrade', $subscription);
        $request->validate(['plan_id' => 'required|exists:subscription_plans,id']);
        $newPlan = SubscriptionPlan::findOrFail($request->plan_id);
        $currentPlan = $subscription->plan;
        if ($newPlan->price >= $currentPlan->price) {
            return response()->json(['message' => '降级计划的价格必须低于当前计划，如需升级请使用升级接口'], 422);
        }
        $user = $request->user();
        $subscription = DB::transaction(function () use ($subscription, $newPlan, $currentPlan, $user) {
            $subscription->update(['plan_id' => $newPlan->id]);
            Log::info('订阅已降级', ['subscription_id' => $subscription->id, 'user_id' => $user->id, 'old_plan_id' => $currentPlan->id, 'new_plan_id' => $newPlan->id]);
            return $subscription;
        });
        return response()->json(['message' => '订阅降级成功', 'data' => $subscription->load('plan')]);
    }

    protected function calculateEndDate(Carbon $startDate, string $billingCycle): Carbon
    {
        return match ($billingCycle) {
            'monthly' => $startDate->copy()->addMonth(),
            'quarterly' => $startDate->copy()->addMonths(3),
            'yearly' => $startDate->copy()->addYear(),
            default => $startDate->copy()->addMonth(),
        };
    }
}
