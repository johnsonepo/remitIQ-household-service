<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\BudgetCategory;
use App\Models\BudgetItem;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Remittance;
use App\Models\TransferProvider;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ------------------------------------------------------------
        // Default budget categories (system-wide, user_id = null)
        // ------------------------------------------------------------
        $categoryNames = ['Food', 'Rent', 'School Fees', 'Medical', 'Utilities', 'Transport'];

        $categories = collect($categoryNames)->map(fn (string $name) => BudgetCategory::firstOrCreate(['name' => $name, 'user_id' => null], ['is_default' => true, 'color' => fake()->hexColor()]));

        // ------------------------------------------------------------
        // Transfer providers
        // ------------------------------------------------------------
        collect(['Western Union', 'MoneyGram', 'Wise', 'WorldRemit', 'Remitly'])->each(fn (string $name) => TransferProvider::firstOrCreate(['name' => $name]));

        $providers = TransferProvider::all();

        // ------------------------------------------------------------
        // A known dev user for manual testing/login
        // ------------------------------------------------------------
        $devUser = User::factory()->create([
            'name' => 'Dev User',
            'username' => 'devuser',
            'email' => 'dev@remitiq.local',
            'country_code' => 'AE',
        ]);

        // ------------------------------------------------------------
        // Senders abroad — each owns a household back home, tracks
        // their own budgets and remittances for it.
        // ------------------------------------------------------------
        $senders = User::factory()->count(5)->create();
        $senders->push($devUser);

        foreach ($senders as $sender) {
            $household = Household::factory()->create([
                'owner_id' => $sender->id,
                'name' => $sender->name."'s Household",
            ]);

            // Owner's own membership record.
            HouseholdMember::factory()->owner()->create([
                'user_id' => $sender->id,
                'household_id' => $household->id,
            ]);

            // A few months of budgets, each with category allocations.
            for ($i = 0; $i < 3; $i++) {
                $budget = Budget::factory()->create([
                    'user_id' => $sender->id,
                    'household_id' => $household->id,
                    'month' => now()->subMonths($i)->startOfMonth(),
                    'currency_code' => $household->base_currency_code,
                ]);

                $categories->random(3)->each(function ($category) use ($budget) {
                    BudgetItem::factory()->create([
                        'budget_id' => $budget->id,
                        'budget_category_id' => $category->id,
                    ]);
                });
            }

            // A handful of remittances this sender made to this household.
            Remittance::factory()->count(4)->create([
                'user_id' => $sender->id,
                'household_id' => $household->id,
                'transfer_provider_id' => fn () => $providers->random()->id,
                'received_currency_code' => $household->base_currency_code,
            ]);
        }

        $this->command->info('Seeded '.$senders->count().' users, each with a household, budgets, and remittances.');
    }
}
