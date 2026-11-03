<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Enums\AssetRole;
use App\Enums\LiabilityDirection;
use App\Enums\LiabilityType;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Ledger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ledger_id' => Ledger::factory(),
            'type' => AccountType::Asset,
            'name' => fake()->unique()->company(),
            'currency_id' => fn (array $attributes) => Ledger::query()->whereKey($attributes['ledger_id'])->value('primary_currency_id'),
            'asset_role' => AssetRole::Default,
        ];
    }

    public function currency(string $code): static
    {
        return $this->state(['currency_id' => Currency::findByCode($code)?->id]);
    }

    public function expense(): static
    {
        return $this->state(['type' => AccountType::Expense, 'currency_id' => null, 'asset_role' => null]);
    }

    public function revenue(): static
    {
        return $this->state(['type' => AccountType::Revenue, 'currency_id' => null, 'asset_role' => null]);
    }

    public function liability(LiabilityDirection $direction = LiabilityDirection::Owed): static
    {
        return $this->state([
            'type' => AccountType::Liability,
            'asset_role' => null,
            'liability_type' => LiabilityType::Loan,
            'liability_direction' => $direction,
        ]);
    }
}
