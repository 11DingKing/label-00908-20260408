<?php

namespace Database\Factories;

use App\Models\Coupon;
use Illuminate\Database\Eloquent\Factories\Factory;

class CouponFactory extends Factory
{
    protected $model = Coupon::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('COUP-####')),
            'name' => $this->faker->words(3, true),
            'type' => $this->faker->randomElement(['percentage', 'fixed']),
            'value' => $this->faker->randomFloat(2, 5, 50),
            'min_amount' => 0,
            'max_discount' => null,
            'currency' => 'CNY',
            'max_uses' => $this->faker->optional()->numberBetween(10, 1000),
            'used_count' => 0,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addMonths(3),
            'is_active' => true,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn() => [
            'valid_from' => now()->subMonths(3),
            'valid_until' => now()->subDay(),
        ]);
    }

    public function percentage(float $value = 10): static
    {
        return $this->state(fn() => [
            'type' => 'percentage',
            'value' => $value,
        ]);
    }

    public function fixed(float $value = 50): static
    {
        return $this->state(fn() => [
            'type' => 'fixed',
            'value' => $value,
        ]);
    }
}
