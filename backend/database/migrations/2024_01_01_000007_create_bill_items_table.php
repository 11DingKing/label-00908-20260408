<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('usage_record_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('item_type', ['subscription', 'usage', 'discount', 'tax'])->comment('项目类型');
            $table->string('description', 500)->comment('描述');
            $table->string('dimension_code', 100)->nullable()->comment('计量维度代码');
            $table->decimal('quantity', 15, 4)->nullable()->comment('数量');
            $table->decimal('unit_price', 10, 4)->nullable()->comment('单价');
            $table->decimal('amount', 10, 2)->default(0)->comment('金额');
            $table->timestamps();

            $table->index('item_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_items');
    }
};
