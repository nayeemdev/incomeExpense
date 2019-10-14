<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Income;
use App\Models\User;
use Faker\Generator as Faker;

$factory->define(Income::class, function (Faker $faker) {
    return [
        'income_title' => $faker->sentence(5),
        'income_amount' => $faker->randomDigit,
        'income_date' => $faker->date('Y-m-d'),
        'user_id' => User::all()->random()->id,
    ];
});
