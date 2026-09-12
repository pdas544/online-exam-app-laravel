<?php

namespace Tests\Unit;

use App\Jobs\GradeExamSession;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeExamSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_grades_completed_session(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $subject = Subject::factory()->create(['created_by' => $teacher->id]);
        $exam = Exam::factory()->create([
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'status' => 'published',
            'available_from' => null,
            'available_to' => null,
            'passing_marks' => 40,
            'time_limit' => 60,
        ]);
        $question = Question::factory()->create([
            'subject_id' => $subject->id,
            'created_by' => $teacher->id,
            'question_type' => 'mcq_single',
            'options' => ['A' => 'First', 'B' => 'Second'],
            'correct_answers' => ['B'],
            'points' => 5,
        ]);
        $exam->questions()->attach($question->id, ['order_index' => 1]);
        $session = ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'status' => 'completed',
            'started_at' => now()->subMinutes(10),
            'submitted_at' => now(),
            'total_questions' => 1,
        ]);
        StudentAnswer::create([
            'exam_session_id' => $session->id,
            'question_id' => $question->id,
            'exam_id' => $exam->id,
            'max_points' => 5,
            'answer' => ['B'],
            'is_answered' => true,
        ]);

        (new GradeExamSession($session->id))->handle(app(\App\Services\GradingService::class));

        $this->assertEquals(100.0, (float) $session->fresh()->score);
        $this->assertTrue((bool) $session->fresh()->passed);
    }

    public function test_job_skips_missing_session(): void
    {
        // Must not throw when the session vanished (deleted exam).
        (new GradeExamSession(999999))->handle(app(\App\Services\GradingService::class));

        $this->assertTrue(true);
    }
}
