<?php

namespace App\Modules\Billing\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Models\Bill;
use App\Modules\Payment\Models\Payment;
use App\Modules\Subscription\Models\UsageRecord;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        $startDate = $request->input('start_date') ? now()->parse($request->input('start_date')) : now()->startOfMonth();
        $endDate = $request->input('end_date') ? now()->parse($request->input('end_date')) : now()->endOfMonth();

        $totalBills = Bill::where('user_id', $user->id)->whereBetween('created_at', [$startDate, $endDate])->sum('total_amount');
        $paidAmount = Payment::where('user_id', $user->id)->where('status', 'completed')->whereBetween('paid_at', [$startDate, $endDate])->sum('amount');
        $pendingBills = Bill::where('user_id', $user->id)->where('status', 'pending')->sum('total_amount');
        $billCount = Bill::where('user_id', $user->id)->whereBetween('created_at', [$startDate, $endDate])->count();
        $totalTax = Bill::where('user_id', $user->id)->whereBetween('created_at', [$startDate, $endDate])->sum('tax');
        $totalDiscount = Bill::where('user_id', $user->id)->whereBetween('created_at', [$startDate, $endDate])->sum('discount');
        $refundedAmount = Payment::where('user_id', $user->id)->whereBetween('created_at', [$startDate, $endDate])->sum('refunded_amount');

        return response()->json([
            'data' => [
                'total_bills' => (float) $totalBills, 'paid_amount' => (float) $paidAmount,
                'pending_amount' => (float) $pendingBills, 'bill_count' => $billCount,
                'total_tax' => (float) $totalTax, 'total_discount' => (float) $totalDiscount,
                'refunded_amount' => (float) $refundedAmount,
                'period' => ['start' => $startDate->toDateString(), 'end' => $endDate->toDateString()],
            ],
        ]);
    }

    public function usage(Request $request): JsonResponse
    {
        $user = $request->user();
        $startDate = $request->input('start_date') ? now()->parse($request->input('start_date')) : now()->startOfMonth();
        $endDate = $request->input('end_date') ? now()->parse($request->input('end_date')) : now()->endOfMonth();
        $usage = UsageRecord::where('user_id', $user->id)->whereBetween('recorded_at', [$startDate, $endDate])
            ->select('dimension_id', DB::raw('SUM(quantity) as total_quantity'))->groupBy('dimension_id')->with('dimension')->get();
        return response()->json(['data' => $usage]);
    }

    public function revenue(Request $request): JsonResponse
    {
        $user = $request->user();
        $startDate = $request->input('start_date') ? now()->parse($request->input('start_date')) : now()->startOfMonth();
        $endDate = $request->input('end_date') ? now()->parse($request->input('end_date')) : now()->endOfMonth();
        $revenue = Payment::where('user_id', $user->id)->where('status', 'completed')->whereBetween('paid_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(paid_at) as date'), DB::raw('SUM(amount) as total'))->groupBy('date')->orderBy('date')->get();
        return response()->json(['data' => $revenue]);
    }

    public function trend(Request $request): JsonResponse
    {
        $user = $request->user();
        $startDate = $request->input('start_date') ? now()->parse($request->input('start_date')) : now()->subDays(30);
        $endDate = $request->input('end_date') ? now()->parse($request->input('end_date')) : now();
        $groupBy = $request->input('group_by', 'day');
        $dateExpr = match ($groupBy) {
            'month' => DB::raw("strftime('%Y-%m', paid_at) as period"),
            'week' => DB::raw("strftime('%Y-%W', paid_at) as period"),
            default => DB::raw("DATE(paid_at) as period"),
        };
        $trend = Payment::where('user_id', $user->id)->where('status', 'completed')->whereBetween('paid_at', [$startDate, $endDate])
            ->select($dateExpr, DB::raw('SUM(amount) as total_amount'), DB::raw('SUM(amount - refunded_amount) as net_amount'), DB::raw('COUNT(*) as payment_count'))
            ->groupBy('period')->orderBy('period')->get();
        return response()->json(['data' => ['period' => ['start' => $startDate->toDateString(), 'end' => $endDate->toDateString()], 'group_by' => $groupBy, 'trend' => $trend]]);
    }

    public function subscriptionStats(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->getSubscriptionStats()]);
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $user = $request->user();
        $startDate = $request->input('start_date') ? now()->parse($request->input('start_date')) : now()->startOfMonth();
        $endDate = $request->input('end_date') ? now()->parse($request->input('end_date')) : now()->endOfMonth();
        $type = $request->input('type', 'bills');
        $filename = "report-{$type}-{$startDate->format('Ymd')}-{$endDate->format('Ymd')}.csv";
        $headers = ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => "attachment; filename=\"{$filename}\""];

        $callback = function () use ($user, $startDate, $endDate, $type) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            if ($type === 'bills') {
                fputcsv($handle, ['账单编号', '订阅计划', '订阅费用', '使用量费用', '折扣', '税费', '总金额', '币种', '状态', '账期开始', '账期结束', '创建时间']);
                Bill::where('user_id', $user->id)->whereBetween('created_at', [$startDate, $endDate])->with('subscription.plan')->orderBy('created_at', 'desc')
                    ->chunk(200, function ($bills) use ($handle) {
                        foreach ($bills as $bill) {
                            fputcsv($handle, [$bill->bill_number, $bill->subscription?->plan?->name ?? '-', $bill->subscription_fee, $bill->usage_fee, $bill->discount, $bill->tax, $bill->total_amount, $bill->currency ?? 'CNY', $bill->status, $bill->period_start?->toDateString(), $bill->period_end?->toDateString(), $bill->created_at->toDateTimeString()]);
                        }
                    });
            } elseif ($type === 'payments') {
                fputcsv($handle, ['支付ID', '账单编号', '支付方式', '网关', '金额', '已退款', '币种', '状态', '交易号', '支付时间']);
                Payment::where('user_id', $user->id)->whereBetween('created_at', [$startDate, $endDate])->with('bill')->orderBy('created_at', 'desc')
                    ->chunk(200, function ($payments) use ($handle) {
                        foreach ($payments as $payment) {
                            fputcsv($handle, [$payment->id, $payment->bill?->bill_number ?? '-', $payment->payment_method, $payment->gateway ?? '-', $payment->amount, $payment->refunded_amount ?? 0, $payment->currency ?? 'CNY', $payment->status, $payment->transaction_id ?? '-', $payment->paid_at?->toDateTimeString() ?? '-']);
                        }
                    });
            } elseif ($type === 'usage') {
                fputcsv($handle, ['维度', '数量', '单位', '记录时间']);
                UsageRecord::where('user_id', $user->id)->whereBetween('recorded_at', [$startDate, $endDate])->with('dimension')->orderBy('recorded_at', 'desc')
                    ->chunk(200, function ($records) use ($handle) {
                        foreach ($records as $record) {
                            fputcsv($handle, [$record->dimension?->name ?? '-', $record->quantity, $record->dimension?->unit ?? '-', $record->recorded_at->toDateTimeString()]);
                        }
                    });
            }
            fclose($handle);
        };
        return response()->stream($callback, 200, $headers);
    }
}
