<?php

namespace Database\Factories;

use App\Models\UsageRecord;
use App\Models\User;
use App\Models\Subscription;
use App\Models\MeteringDimension;
use Illuminate\Database\Eloquent\Factories\Factory;

class UsageRecordFactory extends Factory
{
    protected $model = UsageRecord::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subscription_id' => Subscription::factory(),
            'dimension_id' => MeteringDimension::factory(),
            'quantity' => fake()->randomFloat(4, 1, 1000),
            'recorded_at' => now(),
            'billing_period_start' => now()->startOfMonth(),
            'billing_period_end' => now()->endOfMonth(),
            'metadata' => [],
        ];
    }
}
