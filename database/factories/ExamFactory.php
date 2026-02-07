<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExamFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => $this->faker->words(3, true),
            'description' => $this->faker->paragraph(),
            'subject_id' => Subject::factory(),
            'teacher_id' => User::factory(),
            'academic_year' => $this->faker->year(),
            'semester' => $this->faker->randomElement(['1', '2', '3', '4', '5', '6', '7', '8']),
            'time_limit' => $this->faker->numberBetween(30, 180),
            'shuffle_questions' => $this->faker->boolean(),
            'shuffle_options' => $this->faker->boolean(),
            'available_from' => $this->faker->optional()->dateTimeBetween('now', '+1 week'),
            'available_to' => $this->faker->optional()->dateTimeBetween('+1 week', '+1 month'),
            'total_marks' => 0,
            'passing_marks' => $this->faker->numberBetween(50, 70),
            'max_attempts' => $this->faker->numberBetween(1, 3),
            'status' => $this->faker->randomElement(['draft', 'published', 'archived']),
        ];
    }
}
