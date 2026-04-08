<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('payment_method', ['alipay', 'wechat', 'bank_transfer', 'credit_card'])->comment('支付方式');
            $table->decimal('amount', 10, 2)->comment('支付金额');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'refunded'])->default('pending');
            $table->string('transaction_id')->nullable()->comment('交易号');
            $table->json('payment_data')->nullable()->comment('支付数据');
            $table->datetime('paid_at')->nullable()->comment('支付时间');
            $table->datetime('refunded_at')->nullable()->comment('退款时间');
            $table->text('notes')->nullable()->comment('备注');
            $table->timestamps();

            $table->index('status');
            $table->index('transaction_id');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
