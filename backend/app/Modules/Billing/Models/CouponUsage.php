<?php

namespace App\Modules\Billing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CouponUsage extends Model
{
    protected $fillable = ['coupon_id', 'user_id', 'bill_id', 'discount_amount'];
    protected $table = 'coupon_usages';

    protected function casts(): array
    {
        return ['discount_amount' => 'decimal:2'];
    }

    public function coupon() { return $this->belongsTo(Coupon::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function bill() { return $this->belongsTo(Bill::class); }
}
