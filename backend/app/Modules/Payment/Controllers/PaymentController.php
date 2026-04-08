<?php

namespace App\Modules\Payment\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payment\Requests\CreatePaymentRequest;
use App\Models\Bill;
use App\Models\Payment;
use App\Modules\Payment\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $paymentService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Payment::with('bill')->orderBy('created_at', 'desc');
        if (!$user->isAdmin() && !$user->hasPermission('payments.view')) {
            $query->where('user_id', $user->id);
        }
        $payments = $query->paginate($request->input('per_page', 15));
        return response()->json(['data' => $payments]);
    }

    public function store(CreatePaymentRequest $request): JsonResponse
    {
        $user = $request->user();
        $bill = Bill::where('user_id', $user->id)->where('status', 'pending')->findOrFail($request->bill_id);
        $result = $this->paymentService->createPayment($bill, $request->payment_method, [
            'idempotency_key' => $request->header('Idempotency-Key'),
            'payment_data' => $request->payment_data ?? [],
            'description' => "账单 {$bill->bill_number} 支付",
            'return_url' => $request->input('return_url'),
        ]);
        return response()->json(['message' => '支付订单已创建', 'data' => $result['payment'], 'gateway_data' => $result['gateway_data']], 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $payment = Payment::with('bill')->findOrFail($id);
        $this->authorize('view', $payment);
        return response()->json(['data' => $payment]);
    }

    public function callback(Request $request, $id): JsonResponse
    {
        $payment = Payment::findOrFail($id);
        $transactionId = $request->input('transaction_id');
        $success = $request->input('success', false);
        $this->paymentService->processPaymentCallback($payment, $transactionId, $success);
        return response()->json(['message' => '支付回调处理成功', 'data' => $payment->fresh()]);
    }

    public function refund(Request $request, $id): JsonResponse
    {
        $payment = Payment::findOrFail($id);
        $this->authorize('refund', $payment);
        $request->validate(['amount' => 'required|numeric|min:0.01', 'reason' => 'required|string|max:500']);
        $refund = $this->paymentService->refund($payment, (float) $request->amount, $request->reason);
        return response()->json(['message' => '退款申请已提交', 'data' => $refund], 201);
    }

    public function syncStatus(Request $request, $id): JsonResponse
    {
        $payment = Payment::findOrFail($id);
        $this->authorize('view', $payment);
        $payment = $this->paymentService->syncPaymentStatus($payment);
        return response()->json(['data' => $payment]);
    }
}
