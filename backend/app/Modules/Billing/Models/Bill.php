<?php

namespace App\Modules\Billing\Models;

use App\Models\User;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bill extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'subscription_id', 'bill_number',
        'subscription_fee', 'usage_fee', 'discount', 'coupon_code',
        'tax', 'tax_rate', 'total_amount',
        'currency', 'exchange_rate',
        'status', 'period_start', 'period_end', 'due_date', 'paid_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'subscription_fee' => 'decimal:2',
            'usage_fee' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'total_amount' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function subscription() { return $this->belongsTo(Subscription::class); }
    public function items() { return $this->hasMany(BillItem::class); }
    public function payments() { return $this->hasMany(Payment::class); }

    public function scopePending(Builder $query): Builder { return $query->where('status', 'pending'); }
    public function scopePaid(Builder $query): Builder { return $query->where('status', 'paid'); }
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'overdue')
            ->orWhere(function ($q) {
                $q->where('status', 'pending')->where('due_date', '<', now());
            });
    }
    public function scopeForPeriod(Builder $query, $start, $end): Builder { return $query->whereBetween('period_start', [$start, $end]); }
    public function scopeUnpaid(Builder $query): Builder { return $query->whereIn('status', ['pending', 'overdue']); }

    public function isPaid(): bool { return $this->status === 'paid'; }
    public function isPending(): bool { return $this->status === 'pending'; }
    public function isOverdue(): bool
    {
        if ($this->status === 'overdue') return true;
        if ($this->status === 'pending' && $this->due_date) {
            return $this->due_date->lt(now()->startOfDay());
        }
        return false;
    }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }

    public function markAsPaid() { $this->update(['status' => 'paid', 'paid_at' => now()]); }
    public function markAsOverdue() { $this->update(['status' => 'overdue']); }
    public function cancel() { $this->update(['status' => 'cancelled']); }

    public function calculateTotal(): float { return $this->subscription_fee + $this->usage_fee - $this->discount + $this->tax; }
    public function getOutstandingAmount(): float
    {
        $paidAmount = $this->payments()->where('status', 'completed')->sum('amount');
        return max(0, $this->total_amount - $paidAmount);
    }
}
