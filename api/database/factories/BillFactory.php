<?php

namespace Database\Factories;

use App\Enums\RepeatFrequency;
use App\Models\Bill;
use App\Models\Ledger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bill>
 */
class BillFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ledger_id' => Ledger::factory(),
            'name' => fake()->unique()->company(),
            'currency_id' => fn (array $attributes) => Ledger::query()->whereKey($attributes['ledger_id'])->value('primary_currency_id'),
            'amount_min' => '10',
            'amount_max' => '12',
            'date' => '2026-01-15',
            'repeat_freq' => RepeatFrequency::Monthly,
        ];
    }
}
