<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Events\ExamStartAllowed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Exam $exam;

    private ExamSession $session;

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
            'max_attempts' => 3,
            'time_limit' => 60,
        ]);
        $question = Question::factory()->create([
            'subject_id' => $subject->id,
            'created_by' => $this->teacher->id,
        ]);
        $this->exam->questions()->attach($question->id, ['order_index' => 1]);
        $this->session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => 'scheduled',
            'total_questions' => 1,
        ]);
    }

    public function test_teacher_views_monitor_and_sessions(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('teacher.monitor'))
            ->assertOk();

        $this->actingAs($this->teacher)
            ->get(route('teacher.monitor.exam', $this->exam))
            ->assertOk();

        $this->actingAs($this->teacher)
            ->get(route('teacher.monitor.sessions', $this->exam))
            ->assertOk()
            ->assertJsonPath('sessions.0.id', $this->session->id)
            ->assertJsonStructure(['sessions', 'total_active']);
    }

    public function test_teacher_mass_starts_scheduled_sessions(): void
    {
        Event::fake([ExamStartAllowed::class]);

        $this->actingAs($this->teacher)
            ->postJson(route('teacher.monitor.start', $this->exam))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('started', 1);

        $this->assertEquals('in_progress', $this->session->fresh()->status);
        $this->assertNotNull($this->session->fresh()->started_at);
        // The lobby "Proceed" button depends on this broadcast reaching the
        // student: a dispatch-side regression strands it silently.
        Event::assertDispatched(ExamStartAllowed::class, function ($event) {
            return $event->sessionId === $this->session->id
                && $event->examId === $this->exam->id;
        });
    }

    public function test_teacher_warns_and_resumes_paused_session(): void
    {
        $this->session->update(['status' => 'paused', 'paused_at' => now()]);

        $this->actingAs($this->teacher)
            ->postJson(route('teacher.monitor.warn', $this->session), ['message' => 'Focus!'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($this->teacher)
            ->postJson(route('teacher.monitor.resume', $this->session))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals('in_progress', $this->session->fresh()->status);
    }

    public function test_student_cannot_access_monitoring(): void
    {
        $this->actingAs($this->student)
            ->get(route('teacher.monitor'))
            ->assertForbidden();

        $this->actingAs($this->student)
            ->postJson(route('teacher.monitor.start', $this->exam))
            ->assertForbidden();
    }

    public function test_other_teacher_cannot_monitor_foreign_exam(): void
    {
        $other = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($other)
            ->get(route('teacher.monitor.exam', $this->exam))
            ->assertForbidden();
    }
}
