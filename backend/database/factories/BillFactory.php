<?php

namespace Database\Factories;

use App\Models\Bill;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillFactory extends Factory
{
    protected $model = Bill::class;

    public function definition(): array
    {
        $periodStart = $this->faker->dateTimeBetween('-3 months', '-1 month');
        $periodEnd = (clone $periodStart)->modify('+1 month');
        $subscriptionFee = $this->faker->randomFloat(2, 50, 500);
        $usageFee = $this->faker->randomFloat(2, 0, 200);

        return [
            'user_id' => User::factory(),
            'subscription_id' => Subscription::factory(),
            'bill_number' => 'BILL-' . date('Ymd') . '-' . str_pad($this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'subscription_fee' => $subscriptionFee,
            'usage_fee' => $usageFee,
            'discount' => 0,
            'tax' => 0,
            'total_amount' => $subscriptionFee + $usageFee,
            'status' => $this->faker->randomElement(['pending', 'paid', 'overdue']),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'due_date' => (clone $periodEnd)->modify('+7 days'),
            'paid_at' => null,
            'notes' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'paid_at' => null,
            'due_date' => now()->addDays(7),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'overdue',
            'due_date' => now()->subDays(7),
            'paid_at' => null,
        ]);
    }
}
