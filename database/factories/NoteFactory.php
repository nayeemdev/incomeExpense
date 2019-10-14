<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Note;
use App\Models\User;
use Faker\Generator as Faker;

$factory->define(Note::class, function (Faker $faker) {
    return [
        'note_title' => $faker->sentence(5),
        'note_amount' => $faker->randomDigit,
        'note_date' => $faker->date('Y-m-d'),
        'user_id' => User::all()->random()->id,
    ];
});
