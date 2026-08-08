<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class HouseholdMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'user_id' => User::factory(),
            'role' => fake()->randomElement(['admin', 'member']),
            'joined_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes) => ['role' => 'owner']);
    }
}
