<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('dimension_id')->constrained('metering_dimensions')->restrictOnDelete();
            $table->decimal('quantity', 15, 4)->default(0)->comment('使用量');
            $table->datetime('recorded_at')->comment('记录时间');
            $table->date('billing_period_start')->nullable()->comment('计费周期开始');
            $table->date('billing_period_end')->nullable()->comment('计费周期结束');
            $table->json('metadata')->nullable()->comment('元数据');
            $table->timestamps();

            $table->index('recorded_at');
            $table->index(['billing_period_start', 'billing_period_end']);
            $table->index(['user_id', 'dimension_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');
    }
};
