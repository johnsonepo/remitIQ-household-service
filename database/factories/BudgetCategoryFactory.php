<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->randomElement([
                'Food', 'Rent', 'School Fees', 'Medical', 'Utilities', 'Transport',
            ]),
            'icon' => null,
            'color' => fake()->hexColor(),
            'is_default' => true,
        ];
    }

    public function custom(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory(),
            'is_default' => false,
        ]);
    }
}
