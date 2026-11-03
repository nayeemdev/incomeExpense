<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Ledger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ledger>
 */
class LedgerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'primary_currency_id' => fn () => Currency::findByCode('USD')?->id,
        ];
    }
}
