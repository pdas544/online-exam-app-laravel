<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Subject;
use App\Models\User;
use App\Models\ViolationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogViolationTest extends TestCase
{
    use RefreshDatabase;

    private ExamSession $session;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->student = User::factory()->create(['role' => 'student']);
        $subject = Subject::factory()->create(['created_by' => $teacher->id]);
        $exam = Exam::factory()->create([
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'status' => 'published',
            'available_from' => null,
            'available_to' => null,
            'max_attempts' => 5,
            'time_limit' => 60,
        ]);
        $this->session = ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $this->student->id,
            'teacher_id' => $teacher->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'total_questions' => 0,
        ]);
    }

    public function test_navigation_violation_is_logged_not_500(): void
    {
        $response = $this->actingAs($this->student)->postJson(
            route('exam.session.violation', $this->session),
            ['type' => 'page_navigation', 'description' => 'Reload during exam']
        );

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertEquals(1, ViolationLog::where('violation_type', 'page_navigation')->count());
    }

    public function test_unknown_violation_type_is_rejected(): void
    {
        $this->actingAs($this->student)->postJson(
            route('exam.session.violation', $this->session),
            ['type' => 'made_up_type', 'description' => 'x']
        )->assertStatus(422);
    }
}
