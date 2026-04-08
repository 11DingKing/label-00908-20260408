<?php

namespace App\Modules\Payment\Observers;

use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class PaymentObserver
{
    public function created(Payment $payment): void
    {
        Log::channel('payment')->info('支付记录已创建', [
            'payment_id' => $payment->id, 'user_id' => $payment->user_id,
            'bill_id' => $payment->bill_id, 'amount' => $payment->amount,
            'method' => $payment->payment_method,
        ]);
    }

    public function updated(Payment $payment): void
    {
        if ($payment->wasChanged('status')) {
            Log::channel('payment')->info('支付状态变更', [
                'payment_id' => $payment->id,
                'old_status' => $payment->getOriginal('status'),
                'new_status' => $payment->status,
                'transaction_id' => $payment->transaction_id,
            ]);
            if ($payment->status === 'completed' && $payment->bill) {
                $payment->bill->markAsPaid();
            }
        }
    }
}
