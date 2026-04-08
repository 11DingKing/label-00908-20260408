<?php

namespace App\Modules\Subscription\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Database\Factories\UsageRecordFactory;

class UsageRecord extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return UsageRecordFactory::new();
    }

    protected $fillable = [
        'user_id', 'subscription_id', 'dimension_id', 'quantity',
        'recorded_at', 'billing_period_start', 'billing_period_end', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'recorded_at' => 'datetime',
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'metadata' => 'array',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function subscription() { return $this->belongsTo(Subscription::class); }
    public function dimension() { return $this->belongsTo(MeteringDimension::class, 'dimension_id'); }
    public function billItems() { return $this->hasMany(\App\Models\BillItem::class); }
}
