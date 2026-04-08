<?php

namespace App\Modules\Billing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Bill::with('subscription.plan')->orderBy('created_at', 'desc');
        if (!$user->isAdmin() && !$user->hasPermission('bills.view')) {
            $query->where('user_id', $user->id);
        }
        $bills = $query->paginate($request->input('per_page', 15));
        return response()->json(['data' => $bills]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $bill = Bill::with(['subscription.plan', 'items'])->findOrFail($id);
        $this->authorize('view', $bill);
        return response()->json(['data' => $bill]);
    }

    public function getItems(Request $request, $id): JsonResponse
    {
        $bill = Bill::findOrFail($id);
        $this->authorize('view', $bill);
        return response()->json(['data' => $bill->items()->get()]);
    }

    public function download(Request $request, $id): \Illuminate\Http\Response
    {
        $bill = Bill::with(['subscription.plan', 'items', 'user'])->findOrFail($id);
        $this->authorize('download', $bill);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('bills.pdf', ['bill' => $bill]);
        return $pdf->download("bill-{$bill->bill_number}.pdf");
    }
}
