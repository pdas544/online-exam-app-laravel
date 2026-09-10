<?php

namespace Tests\Feature;

use App\Events\ExamForceEnded;
use App\Events\ExamResumed;
use App\Events\GradingCompleted;
use App\Events\TeacherWarning;
use App\Jobs\GradeExamSession;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\GradingService;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GradingCompletedTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Exam $exam;

    private Question $question;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->student = User::factory()->create(['role' => 'student']);
        $subject = Subject::factory()->create(['created_by' => $this->teacher->id]);
        $this->exam = Exam::factory()->create([
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'status' => 'published',
            'available_from' => null,
            'available_to' => null,
            'passing_marks' => 40,
            'time_limit' => 60,
        ]);
        $this->question = Question::factory()->create([
            'subject_id' => $subject->id,
            'created_by' => $this->teacher->id,
            'question_type' => 'mcq_single',
            'options' => ['A' => 'First', 'B' => 'Second'],
            'correct_answers' => ['B'],
            'points' => 5,
        ]);
        $this->exam->questions()->attach($this->question->id, ['order_index' => 1]);
    }

    private function makeSession(string $status = 'completed', ?float $score = null): ExamSession
    {
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => $status,
            'started_at' => now()->subMinutes(10),
            'submitted_at' => $status === 'completed' ? now() : null,
            'total_questions' => 1,
            'score' => $score,
            'passed' => $score === null ? null : $score >= 40,
        ]);
        StudentAnswer::create([
            'exam_session_id' => $session->id,
            'question_id' => $this->question->id,
            'exam_id' => $this->exam->id,
            'max_points' => 5,
            'answer' => ['B'],
            'is_answered' => true,
        ]);

        return $session;
    }

    public function test_grading_job_broadcasts_completion(): void
    {
        Event::fake();
        $session = $this->makeSession();

        (new GradeExamSession($session->id))->handle(app(GradingService::class));

        $this->assertNotNull($session->fresh()->score);
        Event::assertDispatched(GradingCompleted::class, function ($event) use ($session) {
            return $event->sessionId === $session->id
                && $event->studentId === $this->student->id
                && $event->examId === $this->exam->id
                && $event->score !== null;
        });
    }

    public function test_grading_completed_event_targets_student_channel(): void
    {
        $event = new GradingCompleted($this->makeSession(), 80.0, true);

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertStringContainsString("student.{$this->student->id}", $channels[0]->name);
        $this->assertEquals('grading.completed', $event->broadcastAs());
        $this->assertEquals('broadcasts', $event->broadcastQueue);
    }

    public function test_results_list_flags_grading_pending(): void
    {
        $this->makeSession('completed', 80.0);
        $this->makeSession('completed', null);

        $rows = app(DashboardService::class)->studentResults($this->student->id);

        $this->assertCount(2, $rows);
        $pending = collect($rows)->firstWhere('grading_pending', true);
        $graded = collect($rows)->firstWhere('grading_pending', false);
        $this->assertNotNull($pending);
        $this->assertNotNull($graded);
    }

    public function test_results_index_shows_grading_badge(): void
    {
        $this->makeSession('completed', null);

        $response = $this->actingAs($this->student)->get(route('student.results.index'));

        $response->assertOk()->assertSee('Grading');
    }

    public function test_dashboard_shows_pending_banner(): void
    {
        $this->makeSession('completed', null);

        $response = $this->actingAs($this->student)->get(route('student.dashboard'));

        $response->assertOk()->assertSee('grading');
    }

    public function test_standalone_student_events_use_broadcasts_queue(): void
    {
        $session = $this->makeSession('in_progress');

        $this->assertEquals('broadcasts', (new ExamForceEnded($session))->broadcastQueue);
        $this->assertEquals('broadcasts', (new TeacherWarning($session, 'Heads up'))->broadcastQueue ?? null);
        $this->assertEquals('broadcasts', (new ExamResumed($session->id, $this->student->id))->broadcastQueue ?? null);
    }
}
