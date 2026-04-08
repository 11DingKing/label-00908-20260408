<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->string('currency', 3)->default('CNY')->after('total_amount')->comment('币种');
            $table->decimal('exchange_rate', 10, 6)->default(1.000000)->after('currency')->comment('汇率');
            $table->decimal('tax_rate', 5, 4)->default(0)->after('tax')->comment('税率');
            $table->string('coupon_code', 50)->nullable()->after('discount')->comment('优惠券代码');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('currency', 3)->default('CNY')->after('amount')->comment('币种');
            $table->string('gateway', 50)->nullable()->after('payment_method')->comment('支付网关');
            $table->string('gateway_payment_id')->nullable()->after('transaction_id')->comment('网关支付ID');
            $table->json('gateway_response')->nullable()->after('payment_data')->comment('网关响应');
            $table->string('idempotency_key', 100)->nullable()->unique()->after('gateway_response')->comment('幂等键');
            $table->decimal('refunded_amount', 10, 2)->default(0)->after('amount')->comment('已退款金额');
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->string('currency', 3)->default('CNY')->after('price')->comment('币种');
            $table->decimal('tax_rate', 5, 4)->default(0)->after('currency')->comment('默认税率');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate', 'tax_rate', 'coupon_code']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['currency', 'gateway', 'gateway_payment_id', 'gateway_response', 'idempotency_key', 'refunded_amount']);
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['currency', 'tax_rate']);
        });
    }
};
