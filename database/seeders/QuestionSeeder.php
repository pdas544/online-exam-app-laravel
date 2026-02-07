<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    public function run(): void
    {
        $teachers = User::where('role', 'teacher')->get();
        $subjects = Subject::all();

        if ($subjects->isEmpty()) {
            $this->call(SubjectSeeder::class);
            $subjects = Subject::all();
        }

        // Create sample questions for each subject
        foreach ($subjects as $subject) {
            // Multiple Choice Single
            Question::factory()->count(5)->create([
                'subject_id' => $subject->id,
                'question_type' => 'mcq_single',
                'created_by' => $teachers->random()->id,
            ]);

            // Multiple Choice Multiple
            Question::factory()->count(5)->create([
                'subject_id' => $subject->id,
                'question_type' => 'mcq_multiple',
                'created_by' => $teachers->random()->id,
            ]);

            // True/False
            Question::factory()->count(5)->create([
                'subject_id' => $subject->id,
                'question_type' => 'true_false',
                'created_by' => $teachers->random()->id,
            ]);

            // Fill in the Blank
            Question::factory()->count(5)->create([
                'subject_id' => $subject->id,
                'question_type' => 'fill_blank',
                'created_by' => $teachers->random()->id,
            ]);
        }
    }
}
