<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_id' => SubscriptionPlan::factory(),
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addMonth(),
            'auto_renew' => true,
        ];
    }
}
