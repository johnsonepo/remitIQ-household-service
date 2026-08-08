<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HouseholdFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->lastName() . ' Family',
            'owner_id' => User::factory(),
            'base_currency_code' => fake()->randomElement(['XAF', 'NGN', 'GHS', 'KES']),
            'timezone' => fake()->timezone(),
        ];
    }
}
