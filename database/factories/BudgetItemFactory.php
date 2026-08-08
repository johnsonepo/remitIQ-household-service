<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\BudgetCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class BudgetItemFactory extends Factory
{
    public function definition(): array
    {
        $planned = fake()->randomFloat(2, 20, 400);

        return [
            'budget_id' => Budget::factory(),
            'budget_category_id' => BudgetCategory::factory(),
            'planned_amount' => $planned,
            'actual_amount' => fake()->randomFloat(2, 0, $planned * 1.2),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
