<?php

namespace Tests\Unit;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\User;
use App\Services\ViolationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViolationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExamSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $exam = Exam::factory()->create([
            'teacher_id' => $teacher->id,
            'status' => 'published',
            'available_from' => null,
            'available_to' => null,
        ]);
        $this->session = ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'total_questions' => 0,
        ]);
    }

    public function test_record_persists_violation_with_severity(): void
    {
        $violation = (new ViolationService)->record(
            $this->session->fresh(), 'copy_attempt', 'Copied text', ['field' => 'q1']
        );

        $this->assertEquals(3, $violation->severity);
        $this->assertEquals('Copied text', $violation->description);
        $this->assertEquals(['field' => 'q1'], $violation->metadata);
        $this->assertEquals(1, $this->session->fresh()->violation_count);
    }

    public function test_fifth_violation_auto_terminates_session(): void
    {
        $service = new ViolationService;
        $session = $this->session->fresh();

        for ($i = 0; $i < 4; $i++) {
            $service->record($session->fresh(), 'tab_switch', "switch {$i}");
        }
        $this->assertEquals('in_progress', $session->fresh()->status);

        $last = $service->record($session->fresh(), 'tab_switch', 'switch 4');

        $this->assertEquals('terminated', $session->fresh()->status);
        $this->assertTrue((bool) $last->fresh()->auto_terminated);
    }

    public function test_focus_loss_pauses_in_progress_session(): void
    {
        $paused = (new ViolationService)->pauseOnFocusLoss($this->session->fresh(), 'tab_switch');

        $this->assertTrue($paused);
        $this->assertEquals('paused', $this->session->fresh()->status);
    }

    public function test_non_focus_violation_does_not_pause(): void
    {
        $paused = (new ViolationService)->pauseOnFocusLoss($this->session->fresh(), 'copy_attempt');

        $this->assertFalse($paused);
        $this->assertEquals('in_progress', $this->session->fresh()->status);
    }
}
