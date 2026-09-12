<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeginExamSessionTest extends TestCase
{
    use RefreshDatabase;

    private Exam $exam;

    private User $student;

    private ExamSession $session;

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
        $this->session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'teacher_id' => $teacher->id,
            'status' => 'scheduled',
            'started_at' => null,
            'total_questions' => 0,
        ]);
    }

    public function test_begin_transitions_scheduled_and_returns_server_time(): void
    {
        $response = $this->actingAs($this->student)
            ->postJson(route('exam.session.begin', $this->session));

        $response->assertOk()
            ->assertJsonPath('status', 'in_progress')
            ->assertJsonStructure(['time_remaining']);

        $this->assertEquals('in_progress', $this->session->fresh()->status);
        $this->assertNotNull($this->session->fresh()->started_at);
        $this->assertGreaterThan(0, $response->json('time_remaining'));
    }

    public function test_timer_heartbeat_persists_remaining_time(): void
    {
        $this->session->update(['status' => 'in_progress', 'started_at' => now()]);

        $response = $this->actingAs($this->student)
            ->postJson(route('exam.session.timer', $this->session), ['remaining_time' => 3599]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertEquals(3599, $this->session->fresh()->remaining_time);
        $this->assertNotNull($this->session->fresh()->last_activity_at);
    }

    public function test_timer_heartbeat_rejects_invalid_payload(): void
    {
        $this->session->update(['status' => 'in_progress', 'started_at' => now()]);

        $this->actingAs($this->student)
            ->postJson(route('exam.session.timer', $this->session), ['remaining_time' => -5])
            ->assertStatus(422);
    }

    public function test_timer_heartbeat_forbids_stranger(): void
    {
        $other = User::factory()->create(['role' => 'student']);

        $this->actingAs($other)
            ->postJson(route('exam.session.timer', $this->session), ['remaining_time' => 10])
            ->assertForbidden();
    }

    public function test_submit_paused_session_is_rejected(): void
    {
        $this->session->update(['status' => 'paused', 'started_at' => now()]);

        // The client blocks this earlier with "resume first"; the server
        // stays the backstop so a paused exam can never slip through.
        $this->actingAs($this->student)
            ->postJson(route('exam.session.submit', $this->session))
            ->assertStatus(400);
    }

    public function test_begin_rejects_terminal_session(): void
    {
        $this->session->update(['status' => 'completed', 'submitted_at' => now()]);

        $this->actingAs($this->student)
            ->postJson(route('exam.session.begin', $this->session))
            ->assertStatus(422);
    }
}
