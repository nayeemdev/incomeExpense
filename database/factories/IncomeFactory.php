<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncomeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'income_title' => $this->faker->sentence(5),
            'income_amount' => $this->faker->randomDigit,
            'income_date' => $this->faker->date('Y-m-d'),
            'user_id' => User::all()->random()->id,
        ];
    }
}
