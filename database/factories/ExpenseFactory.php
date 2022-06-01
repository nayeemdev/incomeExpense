<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'expense_title' => $this->faker->sentence(5),
            'expense_amount' => $this->faker->randomDigit,
            'expense_date' => $this->faker->date('Y-m-d'),
            'user_id' => User::all()->random()->id,
        ];
    }
}
