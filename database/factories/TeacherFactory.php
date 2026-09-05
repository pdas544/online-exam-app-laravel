<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Teacher>
 */
class TeacherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory()->teacher()->create();

        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'department' => $this->faker->word(),
            'designation' => $this->faker->word() . ' ' . $this->faker->jobTitle(),
        ];
    }
}
