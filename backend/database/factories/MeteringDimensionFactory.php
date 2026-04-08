<?php

namespace Database\Factories;

use App\Models\MeteringDimension;
use Illuminate\Database\Eloquent\Factories\Factory;

class MeteringDimensionFactory extends Factory
{
    protected $model = MeteringDimension::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'unit' => fake()->randomElement(['次', 'GB', 'MB', '条']),
            'unit_price' => fake()->randomFloat(4, 0.0001, 10),
            'is_active' => true,
        ];
    }
}
