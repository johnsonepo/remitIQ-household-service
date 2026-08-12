<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TransferProviderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement([
                'Western Union', 'MoneyGram', 'Wise', 'WorldRemit', 'Remitly', 'MoMo',
            ]),
            'logo_url' => null,
            'is_active' => true,
        ];
    }
}
