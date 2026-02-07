<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //restrict the length of the name to 100 characters due to postgreSql limit
            'name' => substr($this->faker->words(2, true),0,199),
            'description'=>substr($this->faker->paragraph(),0,254),
            'created_by'=> User::factory(),
        ];
    }
}
