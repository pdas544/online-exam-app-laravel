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

    public function test_begin_rejects_terminal_session(): void
    {
        $this->session->update(['status' => 'completed', 'submitted_at' => now()]);

        $this->actingAs($this->student)
            ->postJson(route('exam.session.begin', $this->session))
            ->assertStatus(422);
    }
}
