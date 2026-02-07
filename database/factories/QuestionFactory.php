<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionFactory extends Factory
{
    public function definition(): array
    {
        $questionTypes = ['mcq_single', 'mcq_multiple', 'true_false', 'fill_blank'];
        $type = $this->faker->randomElement($questionTypes);

        $definition = [
            'subject_id' => Subject::factory(),
            'question_text' => $this->faker->sentence(),
            'question_type' => $type,
            'points' => $this->faker->numberBetween(1, 5),
            'explanation' => $this->faker->optional()->paragraph(),
            'created_by' => User::factory(),
        ];

        switch ($type) {
            case 'mcq_single':
                $options = ['A' => $this->faker->sentence(), 'B' => $this->faker->sentence(),
                    'C' => $this->faker->sentence(), 'D' => $this->faker->sentence()];
                $definition['options'] = $options;
                $definition['correct_answers'] = [$this->faker->randomElement(['A', 'B', 'C', 'D'])];
                break;

            case 'mcq_multiple':
                $options = ['A' => $this->faker->sentence(), 'B' => $this->faker->sentence(),
                    'C' => $this->faker->sentence(), 'D' => $this->faker->sentence()];
                $correct = $this->faker->randomElements(['A', 'B', 'C', 'D'], $this->faker->numberBetween(2, 3));
                $definition['options'] = $options;
                $definition['correct_answers'] = $correct;
                break;

            case 'true_false':
                $definition['correct_answers'] = [$this->faker->randomElement(['true', 'false'])];
                break;

            case 'fill_blank':
                $definition['correct_answers'] = [$this->faker->word()];
                break;
        }

        return $definition;
    }
}
