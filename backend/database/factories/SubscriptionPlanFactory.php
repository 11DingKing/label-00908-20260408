<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'code' => fake()->unique()->slug(),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 10, 500),
            'billing_cycle' => fake()->randomElement(['monthly', 'quarterly', 'yearly']),
            'features' => [],
            'included_usage' => [],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
