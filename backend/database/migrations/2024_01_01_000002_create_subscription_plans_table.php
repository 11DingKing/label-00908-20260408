<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('计划名称');
            $table->string('code', 100)->unique()->comment('计划代码');
            $table->text('description')->nullable()->comment('计划描述');
            $table->decimal('price', 10, 2)->default(0)->comment('价格');
            $table->enum('billing_cycle', ['monthly', 'quarterly', 'yearly'])->default('monthly')->comment('计费周期');
            $table->json('features')->nullable()->comment('功能特性');
            $table->json('included_usage')->nullable()->comment('包含的使用量额度');
            $table->boolean('is_active')->default(true)->comment('是否激活');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
