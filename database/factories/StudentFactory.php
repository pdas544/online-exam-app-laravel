<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory()->student()->create();

        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'roll_number' => $this->faker->unique()->numerify('STU#####'),
            'department' => $this->faker->word(),
            'semester' => $this->faker->numberBetween(1, 8),
            'academic_year' => $this->faker->year('now'),
        ];
    }
}