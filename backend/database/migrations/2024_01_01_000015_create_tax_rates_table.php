<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('税种名称');
            $table->string('code', 50)->unique()->comment('税种代码');
            $table->decimal('rate', 5, 4)->comment('税率，如0.0600表示6%');
            $table->string('region', 50)->default('CN')->comment('适用地区');
            $table->boolean('is_inclusive')->default(false)->comment('是否含税价');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['region', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
