<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array{0: string, 1: string, 2: int}> $currencies */
        $currencies = require database_path('data/currencies.php');
        $now = now();

        DB::table('currencies')->upsert(
            array_map(fn (array $currency) => [
                'id' => strtolower((string) Str::ulid()),
                'code' => $currency[0],
                'name' => $currency[1],
                'decimal_places' => $currency[2],
                'created_at' => $now,
                'updated_at' => $now,
            ], $currencies),
            ['code'],
            ['name', 'decimal_places', 'updated_at'],
        );
    }
}
