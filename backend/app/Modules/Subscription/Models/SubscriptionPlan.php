<?php

namespace App\Modules\Subscription\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Database\Factories\SubscriptionPlanFactory;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return SubscriptionPlanFactory::new();
    }

    protected $fillable = [
        'name', 'code', 'description', 'price', 'currency', 'tax_rate',
        'billing_cycle', 'features', 'included_usage', 'usage_pricing', 'tiered_pricing', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'features' => 'array',
            'included_usage' => 'array',
            'usage_pricing' => 'array',
            'tiered_pricing' => 'array', // 阶梯计费配置
            'is_active' => 'boolean',
        ];
    }

    /**
     * 获取指定维度的单价
     * 优先使用订阅计划的自定义单价，否则返回 null（使用默认单价）
     */
    public function getUnitPriceForDimension(string $dimensionCode): ?string
    {
        $customPricing = $this->usage_pricing ?? [];
        return isset($customPricing[$dimensionCode]) ? (string) $customPricing[$dimensionCode] : null;
    }

    public function subscriptions() { return $this->hasMany(Subscription::class, 'plan_id'); }
    public function scopeActive($query) { return $query->where('is_active', true); }
}
