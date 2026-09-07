<?php

namespace Tests\Feature;

use App\Events\ExamEnded;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ExamFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Exam $exam;

    /** @var array<int> */
    private array $questionIds = [];

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
            'max_attempts' => 2,
            'passing_marks' => 40,
            'time_limit' => 60,
        ]);
        foreach (['B', 'A'] as $i => $correct) {
            $q = Question::factory()->create([
                'subject_id' => $subject->id,
                'created_by' => $this->teacher->id,
                'question_type' => 'mcq_single',
                'options' => ['A' => 'First', 'B' => 'Second'],
                'correct_answers' => [$correct],
                'points' => 5,
            ]);
            $this->exam->questions()->attach($q->id, ['order_index' => $i + 1]);
            $this->questionIds[] = $q->id;
        }
        $this->exam->updateTotalMarks();
    }

    private function openSession(): ExamSession
    {
        $this->actingAs($this->student)->get(route('exam.start', $this->exam));

        return ExamSession::where('exam_id', $this->exam->id)
            ->where('student_id', $this->student->id)
            ->latest('id')->firstOrFail();
    }

    private function completeSession(ExamSession $session, array $answers = ['B', 'A']): void
    {
        $this->actingAs($this->student)
            ->postJson(route('exam.session.begin', $session))->assertOk();
        foreach ($this->questionIds as $i => $qid) {
            $this->actingAs($this->student)->postJson(
                route('exam.session.answer', $session),
                ['question_id' => $qid, 'answer' => [$answers[$i]]]
            )->assertOk();
        }
        $this->actingAs($this->student)
            ->postJson(route('exam.session.submit', $session))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_full_journey_grades_and_redirects_to_result(): void
    {
        $session = $this->openSession();
        $this->completeSession($session);

        $session->refresh();
        $this->assertEquals('completed', $session->status);
        $this->assertEquals(100.0, (float) $session->score);
        $this->assertTrue((bool) $session->passed);

        $this->actingAs($this->student)
            ->get(route('exam.session.result', $session))
            ->assertRedirect(route('student.results.show', $session));
    }

    public function test_double_submit_is_idempotent(): void
    {
        $session = $this->openSession();
        $this->completeSession($session);

        $this->actingAs($this->student)
            ->postJson(route('exam.session.submit', $session))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals(1, ExamSession::where('student_id', $this->student->id)->where('status', 'completed')->count());
    }

    public function test_submit_dispatches_exam_ended(): void
    {
        Event::fake([ExamEnded::class]);

        $session = $this->openSession();
        $this->completeSession($session);

        Event::assertDispatched(ExamEnded::class);
    }

    public function test_max_attempts_blocks_third_start(): void
    {
        $this->completeSession($this->openSession());
        $this->completeSession($this->openSession());

        $this->actingAs($this->student)
            ->get(route('exam.start', $this->exam))
            ->assertRedirect();

        $this->assertEquals(2, ExamSession::where('exam_id', $this->exam->id)->count());
    }

    public function test_teacher_can_force_end_active_session(): void
    {
        $session = $this->openSession();
        $this->actingAs($this->student)
            ->postJson(route('exam.session.begin', $session))->assertOk();

        $this->actingAs($this->teacher)
            ->post(route('teacher.monitor.force-end', $session))
            ->assertRedirect();

        $this->assertEquals('terminated', $session->fresh()->status);
    }

    public function test_student_cannot_force_end(): void
    {
        $session = $this->openSession();

        $this->actingAs($this->student)
            ->post(route('teacher.monitor.force-end', $session))
            ->assertForbidden();
    }
}
