<?php

namespace App\Modules\Billing\Listeners;

use App\Modules\Billing\Events\BillCreated;
use App\Modules\Billing\Mail\BillNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBillNotification implements ShouldQueue
{
    public function handle(BillCreated $event): void
    {
        $bill = $event->bill;
        Log::channel('billing')->info('发送账单通知', [
            'bill_id' => $bill->id,
            'user_id' => $bill->user_id,
            'total_amount' => $bill->total_amount,
        ]);
        Mail::to($bill->user)->send(new BillNotification($bill));
    }
}
