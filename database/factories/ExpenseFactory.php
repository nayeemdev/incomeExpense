<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Expense;
use App\Models\User;
use Faker\Generator as Faker;

$factory->define(Expense::class, function (Faker $faker) {
    return [
        'expense_title' => $faker->sentence(5),
        'expense_amount' => $faker->randomDigit,
        'expense_date' => $faker->date('Y-m-d'),
        'user_id' => User::all()->random()->id,
    ];
});
