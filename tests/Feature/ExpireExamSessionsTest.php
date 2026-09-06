<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireExamSessionsTest extends TestCase
{
    use RefreshDatabase;

    private Exam $exam;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->student = User::factory()->create(['role' => 'student']);
        $subject = Subject::factory()->create(['created_by' => $teacher->id]);
        $this->exam = Exam::factory()->create([
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'status' => 'published',
            'available_from' => null,
            'available_to' => null,
            'max_attempts' => 5,
            'time_limit' => 60,
        ]);
    }

    private function makeSession(string $status, ?string $startedAt): ExamSession
    {
        return ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'teacher_id' => $this->exam->teacher_id,
            'status' => $status,
            'started_at' => $startedAt,
            'total_questions' => 0,
        ]);
    }

    public function test_command_expires_timed_out_sessions(): void
    {
        $overdue = $this->makeSession('in_progress', now()->subMinutes(61)->toDateTimeString());
        $fresh = $this->makeSession('in_progress', now()->toDateTimeString());

        $this->artisan('exams:expire-sessions')->assertSuccessful();

        $this->assertEquals('expired', $overdue->fresh()->status);
        $this->assertNotNull($overdue->fresh()->submitted_at);
        $this->assertEquals('in_progress', $fresh->fresh()->status);
    }

    public function test_command_reports_count(): void
    {
        $this->makeSession('in_progress', now()->subMinutes(61)->toDateTimeString());

        $this->artisan('exams:expire-sessions')->expectsOutputToContain('1');
    }
}
