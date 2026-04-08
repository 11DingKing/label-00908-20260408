<?php

namespace Database\Factories;

use App\Models\TaxRate;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxRateFactory extends Factory
{
    protected $model = TaxRate::class;

    public function definition(): array
    {
        return [
            'name' => '增值税',
            'code' => $this->faker->unique()->slug(2),
            'rate' => 0.0600,
            'region' => 'CN',
            'is_inclusive' => false,
            'is_active' => true,
        ];
    }
}
