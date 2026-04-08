<?php

namespace App\Modules\Billing\Observers;

use App\Models\Bill;
use Illuminate\Support\Facades\Log;

class BillObserver
{
    public function created(Bill $bill): void
    {
        Log::channel('billing')->info('账单已创建', [
            'bill_id' => $bill->id, 'bill_number' => $bill->bill_number,
            'user_id' => $bill->user_id, 'total_amount' => $bill->total_amount,
        ]);
    }

    public function updated(Bill $bill): void
    {
        if ($bill->wasChanged('status')) {
            Log::channel('billing')->info('账单状态变更', [
                'bill_id' => $bill->id,
                'old_status' => $bill->getOriginal('status'),
                'new_status' => $bill->status,
            ]);
            if ($bill->status === 'paid' && $bill->subscription) {
                $bill->subscription->update(['status' => 'active']);
            }
        }
    }
}
