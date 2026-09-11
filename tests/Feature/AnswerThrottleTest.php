<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\ExamSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnswerThrottleTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Exam $exam;

    private Question $question;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('rate_limits.exam_answers', 2);

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
        $this->question = Question::factory()->create([
            'subject_id' => $subject->id,
            'created_by' => $teacher->id,
            'question_type' => 'mcq_single',
            'options' => ['A' => 'First', 'B' => 'Second'],
            'correct_answers' => ['B'],
            'points' => 5,
        ]);
        $this->exam->questions()->attach($this->question->id, ['order_index' => 1]);
    }

    private function openSession(?User $student = null): ExamSession
    {
        $student ??= $this->student;
        $session = (new ExamSessionService)->start($this->exam, $student->id);
        $session->update(['status' => 'in_progress', 'started_at' => now()]);

        return $session->fresh();
    }

    private function postAnswer(ExamSession $session, User $student)
    {
        return $this->actingAs($student)->postJson(
            route('exam.session.answer', $session),
            ['question_id' => $this->question->id, 'answer' => ['B']]
        );
    }

    public function test_answer_endpoint_is_rate_limited(): void
    {
        $session = $this->openSession();

        $this->postAnswer($session, $this->student)->assertOk();
        $this->postAnswer($session, $this->student)->assertOk();
        $this->postAnswer($session, $this->student)->assertStatus(429);
    }

    public function test_answer_limit_is_scoped_to_session(): void
    {
        $first = $this->openSession();
        $otherStudent = User::factory()->create(['role' => 'student']);
        $second = $this->openSession($otherStudent);

        $this->postAnswer($first, $this->student)->assertOk();
        $this->postAnswer($first, $this->student)->assertOk();
        $this->postAnswer($first, $this->student)->assertStatus(429);

        // Same IP, different session: unaffected.
        $this->postAnswer($second, $otherStudent)->assertOk();
    }
}
