<?php

namespace App\Modules\Payment\Listeners;

use App\Modules\Payment\Events\PaymentCompleted;
use App\Modules\Payment\Mail\PaymentSuccessNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendPaymentNotification implements ShouldQueue
{
    public function handle(PaymentCompleted $event): void
    {
        $payment = $event->payment;
        Log::channel('payment')->info('发送支付成功通知', [
            'payment_id' => $payment->id,
            'user_id' => $payment->user_id,
            'amount' => $payment->amount,
        ]);
        Mail::to($payment->user)->send(new PaymentSuccessNotification($payment));
    }
}
