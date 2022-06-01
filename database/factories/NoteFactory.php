<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'note_title' => $this->faker->sentence(5),
            'note_amount' => $this->faker->randomDigit,
            'note_date' => $this->faker->date('Y-m-d'),
            'user_id' => User::all()->random()->id,
        ];
    }
}
