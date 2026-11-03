<?php

namespace Database\Factories;

use App\Models\Ledger;
use App\Models\PiggyBank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PiggyBank>
 */
class PiggyBankFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ledger_id' => Ledger::factory(),
            'name' => fake()->unique()->word(),
            'currency_id' => fn (array $attributes) => Ledger::query()->whereKey($attributes['ledger_id'])->value('primary_currency_id'),
        ];
    }
}
