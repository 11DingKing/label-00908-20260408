<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('优惠券代码');
            $table->string('name')->comment('优惠券名称');
            $table->enum('type', ['percentage', 'fixed'])->comment('折扣类型');
            $table->decimal('value', 10, 2)->comment('折扣值');
            $table->decimal('min_amount', 10, 2)->default(0)->comment('最低消费金额');
            $table->decimal('max_discount', 10, 2)->nullable()->comment('最大折扣金额');
            $table->string('currency', 3)->default('CNY')->comment('适用币种');
            $table->integer('max_uses')->nullable()->comment('最大使用次数');
            $table->integer('used_count')->default(0)->comment('已使用次数');
            $table->datetime('valid_from')->comment('生效时间');
            $table->datetime('valid_until')->comment('失效时间');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['code', 'is_active']);
        });

        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->decimal('discount_amount', 10, 2)->comment('实际折扣金额');
            $table->timestamps();

            $table->index(['coupon_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupons');
    }
};
