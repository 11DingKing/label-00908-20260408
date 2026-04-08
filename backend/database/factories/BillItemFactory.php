<?php

namespace Database\Factories;

use App\Models\BillItem;
use App\Models\Bill;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillItemFactory extends Factory
{
    protected $model = BillItem::class;

    public function definition(): array
    {
        return [
            'bill_id' => Bill::factory(),
            'item_type' => $this->faker->randomElement(['subscription', 'usage']),
            'description' => $this->faker->sentence(),
            'quantity' => $this->faker->numberBetween(1, 100),
            'unit_price' => $this->faker->randomFloat(2, 0.01, 10),
            'amount' => $this->faker->randomFloat(2, 10, 500),
        ];
    }

    public function subscription(): static
    {
        return $this->state(fn (array $attributes) => [
            'item_type' => 'subscription',
            'description' => '订阅费用',
        ]);
    }

    public function usage(): static
    {
        return $this->state(fn (array $attributes) => [
            'item_type' => 'usage',
            'description' => '使用量费用',
            'dimension_code' => 'api_calls',
        ]);
    }
}
