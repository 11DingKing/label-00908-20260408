<?php

namespace App\Modules\Payment\Models;

use App\Models\User;
use App\Models\Bill;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Database\Factories\PaymentFactory;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory()
    {
        return PaymentFactory::new();
    }

    protected $fillable = [
        'user_id', 'bill_id', 'payment_method', 'amount', 'refunded_amount',
        'currency', 'gateway', 'status', 'transaction_id', 'gateway_payment_id',
        'payment_data', 'gateway_response', 'idempotency_key',
        'paid_at', 'refunded_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'payment_data' => 'array',
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function bill() { return $this->belongsTo(Bill::class); }
    public function refunds() { return $this->hasMany(Refund::class); }

    public function isCompleted(): bool { return $this->status === 'completed'; }

    public function markAsCompleted($transactionId = null)
    {
        $this->update([
            'status' => 'completed',
            'transaction_id' => $transactionId ?? $this->transaction_id,
            'paid_at' => now(),
        ]);
        if ($this->bill) {
            $this->bill->markAsPaid();
        }
    }

    public function getRefundableAmount(): float
    {
        return max(0, $this->amount - ($this->refunded_amount ?? 0));
    }
}
