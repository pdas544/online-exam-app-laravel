<?php

namespace Tests\Unit;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\ExamSessionService;
use App\Services\GradingService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamSessionServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Exam $exam;

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
            'max_attempts' => 1,
            'time_limit' => 60,
        ]);
        Question::factory()->count(2)->create([
            'subject_id' => $subject->id,
            'created_by' => $this->teacher->id,
        ]);
        $this->exam->questions()->attach(
            Question::where('subject_id', $subject->id)->pluck('id')->mapWithKeys(
                fn ($id, $i) => [$id => ['order_index' => $i + 1]]
            )->toArray()
        );
    }

    private function service(): ExamSessionService
    {
        return new ExamSessionService(new GradingService);
    }

    public function test_start_creates_session_with_one_answer_per_question(): void
    {
        $session = $this->service()->start($this->exam, $this->student->id);

        $this->assertEquals('scheduled', $session->status);
        $this->assertEquals(2, $session->answers()->count());
        $this->assertEquals($this->exam->teacher_id, $session->teacher_id);
    }

    public function test_start_returns_existing_active_session(): void
    {
        $first = $this->service()->start($this->exam, $this->student->id);

        $second = $this->service()->start($this->exam, $this->student->id);

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, ExamSession::where('exam_id', $this->exam->id)->count());
    }

    public function test_start_throws_when_attempts_exhausted(): void
    {
        $session = $this->service()->start($this->exam, $this->student->id);
        $session->update(['status' => 'in_progress', 'started_at' => now()]);
        $this->service()->submit($session->fresh());

        $this->expectException(DomainException::class);
        $this->service()->start($this->exam, $this->student->id);
    }

    public function test_start_throws_when_exam_unavailable(): void
    {
        $this->exam->update(['status' => 'draft']);

        $this->expectException(DomainException::class);
        $this->service()->start($this->exam->fresh(), $this->student->id);
    }

    public function test_submit_grades_and_marks_completed(): void
    {
        $session = $this->service()->start($this->exam, $this->student->id);
        $session->update(['status' => 'in_progress', 'started_at' => now()]);

        $result = $this->service()->submit($session->fresh());

        $this->assertEquals('completed', $session->fresh()->status);
        $this->assertNotNull($session->fresh()->submitted_at);
        $this->assertArrayHasKey('percentage', $result);
        $this->assertArrayHasKey('passed', $result);
    }

    public function test_submit_is_idempotent(): void
    {
        $session = $this->service()->start($this->exam, $this->student->id);
        $session->update(['status' => 'in_progress', 'started_at' => now()]);

        $first = $this->service()->submit($session->fresh());
        $second = $this->service()->submit($session->fresh());

        $this->assertEquals($first['percentage'], $second['percentage']);
        $this->assertEquals(1, ExamSession::where('exam_id', $this->exam->id)->count());
    }

    public function test_submit_rejects_non_active_session(): void
    {
        $session = $this->service()->start($this->exam, $this->student->id);

        $this->expectException(DomainException::class);
        $this->service()->submit($session->fresh());
    }

    public function test_force_end_terminates_session(): void
    {
        $session = $this->service()->start($this->exam, $this->student->id);
        $session->update(['status' => 'in_progress', 'started_at' => now()]);

        $this->service()->forceEnd($session->fresh());

        $this->assertEquals('terminated', $session->fresh()->status);
        $this->assertNotNull($session->fresh()->submitted_at);
    }

    public function test_expire_overdue_marks_timed_out_sessions_expired(): void
    {
        $timedOut = $this->service()->start($this->exam, $this->student->id);
        $timedOut->update(['status' => 'in_progress', 'started_at' => now()->subHours(5)]);

        $fresh = $this->service()->start($this->exam, User::factory()->create(['role' => 'student'])->id);
        $fresh->update(['status' => 'in_progress', 'started_at' => now()]);

        $count = $this->service()->expireOverdue();

        $this->assertEquals(1, $count);
        $this->assertEquals('expired', $timedOut->fresh()->status);
        $this->assertEquals('in_progress', $fresh->fresh()->status);
    }
}
