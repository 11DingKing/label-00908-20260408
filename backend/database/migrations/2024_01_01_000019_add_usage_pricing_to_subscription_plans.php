<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为订阅计划添加自定义使用量单价字段
 * 
 * 支持不同订阅等级享有不同的使用量单价
 * 例如：高级会员的 API 调用单价比普通会员更低
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            // 自定义使用量单价，格式: {"api_calls": 0.008, "storage": 0.05}
            // 如果某维度未设置，则使用 metering_dimensions 表中的默认单价
            $table->json('usage_pricing')->nullable()->after('included_usage')
                ->comment('自定义使用量单价（按维度code），未设置则使用默认单价');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('usage_pricing');
        });
    }
};
