<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'bill_id', 'usage_record_id', 'item_type', 'description',
        'dimension_code', 'quantity', 'unit_price', 'amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }

    public function bill() { return $this->belongsTo(\App\Models\Bill::class); }
    public function usageRecord() { return $this->belongsTo(\App\Models\UsageRecord::class); }
}
