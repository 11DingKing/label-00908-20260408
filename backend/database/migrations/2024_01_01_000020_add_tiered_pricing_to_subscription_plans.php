<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为订阅计划添加阶梯计费配置字段
 * 
 * 支持两种阶梯计费模式：
 * - volume: 总量阶梯（根据总量确定单价）
 * - graduated: 累进阶梯（分段计费）
 * 
 * 配置示例：
 * {
 *     "api_calls": {
 *         "type": "graduated",
 *         "tiers": [
 *             {"up_to": 10000, "unit_price": "0.10"},
 *             {"up_to": 100000, "unit_price": "0.08"},
 *             {"up_to": null, "unit_price": "0.05"}
 *         ]
 *     }
 * }
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->json('tiered_pricing')->nullable()->after('usage_pricing')
                ->comment('阶梯计费配置（按维度code），支持 volume 和 graduated 两种模式');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('tiered_pricing');
        });
    }
};
