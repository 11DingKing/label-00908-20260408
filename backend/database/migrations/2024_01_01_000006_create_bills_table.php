<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('bill_number', 100)->unique()->comment('账单号');
            $table->decimal('subscription_fee', 10, 2)->default(0)->comment('订阅费用');
            $table->decimal('usage_fee', 10, 2)->default(0)->comment('使用量费用');
            $table->decimal('discount', 10, 2)->default(0)->comment('折扣');
            $table->decimal('tax', 10, 2)->default(0)->comment('税费');
            $table->decimal('total_amount', 10, 2)->default(0)->comment('总金额');
            $table->enum('status', ['pending', 'paid', 'overdue', 'cancelled'])->default('pending');
            $table->date('period_start')->comment('计费周期开始');
            $table->date('period_end')->comment('计费周期结束');
            $table->date('due_date')->comment('到期日期');
            $table->datetime('paid_at')->nullable()->comment('支付时间');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();

            $table->index('status');
            $table->index(['period_start', 'period_end']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
