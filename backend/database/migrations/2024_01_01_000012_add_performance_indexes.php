<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 优化账单查询性能
        Schema::table('bills', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'due_date']);
        });

        // 优化使用量记录查询性能
        Schema::table('usage_records', function (Blueprint $table) {
            $table->index(['user_id', 'recorded_at']);
            $table->index(['dimension_id', 'recorded_at']);
        });

        // 优化支付记录查询性能
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['user_id', 'paid_at']);
            $table->index(['status', 'created_at']);
        });

        // 优化订阅查询性能
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['status', 'end_date']);
            $table->index(['user_id', 'status', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['status', 'due_date']);
        });

        Schema::table('usage_records', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'recorded_at']);
            $table->dropIndex(['dimension_id', 'recorded_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'paid_at']);
            $table->dropIndex(['status', 'created_at']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['status', 'end_date']);
            $table->dropIndex(['user_id', 'status', 'end_date']);
        });
    }
};
