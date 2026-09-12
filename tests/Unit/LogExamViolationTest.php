<?php

namespace Tests\Unit;

use App\Events\ViolationDetected;
use App\Jobs\GradeExamSession;
use App\Jobs\LogExamViolation;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Models\ViolationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LogExamViolationTest extends TestCase
{
    use RefreshDatabase;

    private ExamSession $session;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
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
        Question::factory()->count(1)->create([
            'subject_id' => $subject->id,
            'created_by' => $teacher->id,
        ]);
        $this->session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'total_questions' => 1,
        ]);
    }

    public function test_job_persists_violation_and_bumps_counter(): void
    {
        Event::fake();

        (new LogExamViolation($this->session->id, 'tab_switch', 'Left the tab', []))->handle(
            app(\App\Services\ViolationService::class)
        );

        $this->assertEquals(1, ViolationLog::where('violation_type', 'tab_switch')->count());
        $this->assertEquals(1, $this->session->fresh()->violation_count);
        Event::assertDispatched(ViolationDetected::class);
    }

    public function test_job_pauses_on_focus_loss(): void
    {
        Event::fake();

        (new LogExamViolation($this->session->id, 'window_blur', 'Blur', []))->handle(
            app(\App\Services\ViolationService::class)
        );

        $this->assertEquals('paused', $this->session->fresh()->status);
    }

    public function test_job_auto_terminates_at_fifth_violation(): void
    {
        Event::fake();
        $this->session->update(['violation_count' => 4]);

        (new LogExamViolation($this->session->id, 'tab_switch', 'Again', []))->handle(
            app(\App\Services\ViolationService::class)
        );

        $this->assertEquals('terminated', $this->session->fresh()->status);
    }

    public function test_job_skips_missing_session(): void
    {
        Event::fake();

        (new LogExamViolation(999999, 'tab_switch', 'Ghost', []))->handle(
            app(\App\Services\ViolationService::class)
        );

        $this->assertEquals(0, ViolationLog::count());
        Event::assertNotDispatched(ViolationDetected::class);
    }

    public function test_violation_job_runs_on_violations_queue(): void
    {
        $job = new LogExamViolation($this->session->id, 'tab_switch', 'x', []);

        $this->assertEquals('violations', $job->queue);
    }

    public function test_grading_job_runs_on_grading_queue_with_retries(): void
    {
        $job = new GradeExamSession($this->session->id);

        $this->assertEquals('grading', $job->queue);
        $this->assertEquals(3, $job->tries);
    }

    public function test_exam_events_broadcast_on_broadcasts_queue(): void
    {
        $violation = ViolationLog::create([
            'student_id' => $this->session->student_id,
            'exam_id' => $this->exam->id,
            'exam_session_id' => $this->session->id,
            'violation_type' => 'tab_switch',
            'description' => 'x',
            'severity' => 1,
        ]);

        $this->assertEquals('broadcasts', (new ViolationDetected($violation))->broadcastQueue);
    }
}
