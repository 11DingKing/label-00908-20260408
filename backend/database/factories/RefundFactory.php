<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'user_id' => User::factory(),
            'refund_number' => 'REF-' . date('Ymd') . '-' . str_pad($this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'currency' => 'CNY',
            'type' => $this->faker->randomElement(['full', 'partial']),
            'status' => 'pending',
            'reason' => $this->faker->sentence(),
        ];
    }
}
