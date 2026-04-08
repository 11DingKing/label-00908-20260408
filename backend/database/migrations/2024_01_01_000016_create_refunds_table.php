<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('refund_number', 100)->unique()->comment('退款编号');
            $table->decimal('amount', 10, 2)->comment('退款金额');
            $table->string('currency', 3)->default('CNY');
            $table->enum('type', ['full', 'partial'])->comment('退款类型');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('reason')->comment('退款原因');
            $table->string('gateway_refund_id')->nullable()->comment('网关退款ID');
            $table->json('gateway_response')->nullable()->comment('网关响应');
            $table->datetime('refunded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'status']);
            $table->index('refund_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
