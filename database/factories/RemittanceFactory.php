<?php

namespace Database\Factories;

use App\Models\Household;
use App\Models\TransferProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RemittanceFactory extends Factory
{
    public function definition(): array
    {
        $amountSent = fake()->randomFloat(2, 50, 1000);
        $rate = fake()->randomFloat(4, 560, 630);

        return [
            'user_id' => User::factory(),
            'household_id' => Household::factory(),
            'transfer_provider_id' => TransferProvider::factory(),
            'amount_sent' => $amountSent,
            'sent_currency_code' => 'USD',
            'amount_received' => round($amountSent * $rate, 2),
            'received_currency_code' => 'XAF',
            'exchange_rate' => $rate,
            'rate_source' => fake()->randomElement(['market-service-official', 'market-service-community', null]),
            'sent_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
