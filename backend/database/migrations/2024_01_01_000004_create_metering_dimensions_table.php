<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metering_dimensions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique()->comment('维度代码');
            $table->string('name')->comment('维度名称');
            $table->text('description')->nullable()->comment('描述');
            $table->string('unit', 50)->comment('单位');
            $table->decimal('unit_price', 10, 4)->default(0)->comment('单价');
            $table->boolean('is_active')->default(true)->comment('是否激活');
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metering_dimensions');
    }
};
