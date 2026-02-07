<?php

namespace Database\Seeders;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $teachers = User::where('role', 'teacher')->get();

        if ($teachers->isEmpty()) {
            $teachers = User::factory()->count(3)->create(['role' => 'teacher']);
        }

        $subjects = [
            ['name' => 'Mathematics', 'description' => 'Study of numbers, quantities, and shapes'],
            ['name' => 'Physics', 'description' => 'Study of matter, energy, and their interactions'],
            ['name' => 'Chemistry', 'description' => 'Study of substances and their transformations'],
            ['name' => 'Biology', 'description' => 'Study of living organisms'],
            ['name' => 'Computer Science', 'description' => 'Study of computation and information'],
            ['name' => 'History', 'description' => 'Study of past events'],
            ['name' => 'Geography', 'description' => 'Study of Earth\'s landscapes and environments'],
            ['name' => 'English Literature', 'description' => 'Study of written works in English'],
        ];

        foreach ($subjects as $subjectData) {
            Subject::create([
                'name' => $subjectData['name'],
                'description' => $subjectData['description'],
                'created_by' => $teachers->random()->id,
            ]);
        }

        // Create additional random subjects
        Subject::factory()->count(10)->create();
    }
}
