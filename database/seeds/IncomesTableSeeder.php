<?php

use Illuminate\Database\Seeder;

class IncomesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\Models\Income::class, 50)->create();
    }
}
