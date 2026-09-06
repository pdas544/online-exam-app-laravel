<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_with_upcoming_exams(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $subject = Subject::factory()->create(['created_by' => $teacher->id]);
        $exam = Exam::factory()->create([
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'status' => 'published',
            'available_from' => null,
            'available_to' => null,
        ]);
        $question = Question::factory()->create([
            'subject_id' => $subject->id,
            'created_by' => $teacher->id,
        ]);
        $exam->questions()->attach($question->id, ['order_index' => 1]);

        $this->actingAs($teacher)
            ->get(route('teacher.dashboard'))
            ->assertOk();
    }
}
