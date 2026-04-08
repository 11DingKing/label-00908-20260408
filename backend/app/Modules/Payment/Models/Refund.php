<?php

namespace App\Modules\Payment\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Database\Factories\RefundFactory;

class Refund extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory()
    {
        return RefundFactory::new();
    }

    protected $fillable = [
        'payment_id', 'user_id', 'refund_number', 'amount', 'currency', 'type',
        'status', 'reason', 'gateway_refund_id', 'gateway_response', 'refunded_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'refunded_at' => 'datetime',
        ];
    }

    public function payment() { return $this->belongsTo(Payment::class); }
    public function user() { return $this->belongsTo(User::class); }

    public function markAsCompleted(string $gatewayRefundId = null): void
    {
        $this->update([
            'status' => 'completed',
            'gateway_refund_id' => $gatewayRefundId,
            'refunded_at' => now(),
        ]);
    }
}
