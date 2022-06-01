<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\User::factory(1)->create();
        \App\Models\Expense::factory(50)->create();
        \App\Models\Income::factory(50)->create();
        \App\Models\Note::factory(50)->create();
    }
}
