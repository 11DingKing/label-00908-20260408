<?php

namespace App\Modules\Subscription\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Subscription\Requests\RecordUsageRequest;
use App\Models\MeteringDimension;
use App\Modules\Subscription\Services\UsageService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    public function __construct(protected UsageService $usageService) {}

    public function record(RecordUsageRequest $request): JsonResponse
    {
        $usageRecord = $this->usageService->recordUsage($request->user(), $request->dimension_code, $request->quantity, $request->metadata ?? []);
        return response()->json(['message' => '使用量记录成功', 'data' => $usageRecord->load('dimension')], 201);
    }

    public function getDimensions(Request $request): JsonResponse
    {
        return response()->json(['data' => MeteringDimension::active()->get()]);
    }

    public function getRecords(Request $request): JsonResponse
    {
        $records = $request->user()->usageRecords()->with('dimension', 'subscription')->orderBy('recorded_at', 'desc')->paginate($request->input('per_page', 15));
        return response()->json(['data' => $records]);
    }

    public function getStatistics(Request $request): JsonResponse
    {
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date'))->startOfDay() : null;
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date'))->endOfDay() : null;
        return response()->json(['data' => $this->usageService->getUsageStatistics($request->user(), $startDate, $endDate)]);
    }
}
