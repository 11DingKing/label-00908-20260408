<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Exceptions\InvalidCouponException;
use App\Models\Bill;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\User;

class CouponService
{
    public function applyCoupon(string $code, float $amount, User $user): array
    {
        $coupon = Coupon::where('code', $code)->first();
        if (!$coupon) {
            throw new InvalidCouponException('优惠券不存在');
        }
        if (!$coupon->isValid()) {
            throw new InvalidCouponException('优惠券已过期或已用完');
        }
        if ($amount < $coupon->min_amount) {
            throw new InvalidCouponException("订单金额未达到最低消费 {$coupon->min_amount}");
        }
        $userUsageCount = CouponUsage::where('coupon_id', $coupon->id)->where('user_id', $user->id)->count();
        if ($userUsageCount > 0) {
            throw new InvalidCouponException('您已使用过此优惠券');
        }
        $discount = $coupon->calculateDiscount($amount);
        return [
            'coupon_id' => $coupon->id, 'coupon_code' => $coupon->code,
            'discount_amount' => $discount, 'type' => $coupon->type, 'value' => (float) $coupon->value,
        ];
    }

    public function recordUsage(Coupon $coupon, User $user, Bill $bill, float $discountAmount): void
    {
        CouponUsage::create([
            'coupon_id' => $coupon->id, 'user_id' => $user->id,
            'bill_id' => $bill->id, 'discount_amount' => $discountAmount,
        ]);
        $coupon->increment('used_count');
    }
}
