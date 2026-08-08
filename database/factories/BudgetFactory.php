<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'household_id' => Household::factory(),
            'month' => fake()->dateTimeBetween('-6 months', 'now')->modify('first day of this month'),
            'currency_code' => 'XAF',
            'total_planned' => fake()->randomFloat(2, 200, 2000),
        ];
    }
}
