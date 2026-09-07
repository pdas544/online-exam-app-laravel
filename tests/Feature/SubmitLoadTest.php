<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubmitLoadTest extends TestCase
{
    use RefreshDatabase;

    private Exam $exam;

    private array $questionIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $teacher = User::factory()->create(['role' => 'teacher']);
        $subject = Subject::factory()->create(['created_by' => $teacher->id]);
        $this->exam = Exam::factory()->create([
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'status' => 'published',
            'available_from' => null,
            'available_to' => null,
            'max_attempts' => 5,
            'passing_marks' => 40,
            'time_limit' => 60,
        ]);
        foreach ([5, 5] as $i => $points) {
            $q = Question::factory()->create([
                'subject_id' => $subject->id,
                'created_by' => $teacher->id,
                'question_type' => 'mcq_single',
                'options' => ['A' => 'First', 'B' => 'Second'],
                'correct_answers' => ['B'],
                'points' => $points,
            ]);
            $this->exam->questions()->attach($q->id, ['order_index' => $i + 1]);
            $this->questionIds[] = $q->id;
        }
        $this->exam->updateTotalMarks();
    }

    public function test_one_submit_stays_within_query_budget(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $student->id,
            'teacher_id' => $this->exam->teacher_id,
            'status' => 'in_progress',
            'started_at' => now(),
            'total_questions' => 2,
        ]);
        foreach ($this->questionIds as $qid) {
            \App\Models\StudentAnswer::create([
                'exam_session_id' => $session->id,
                'question_id' => $qid,
                'exam_id' => $this->exam->id,
                'max_points' => 5,
            ]);
        }
        foreach ($this->questionIds as $qid) {
            $this->actingAs($student)->postJson(
                route('exam.session.answer', $session),
                ['question_id' => $qid, 'answer' => ['B']]
            )->assertOk();
        }

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->actingAs($student)
            ->postJson(route('exam.session.submit', $session))
            ->assertOk();

        fwrite(STDERR, "\n[perf] submit queries: {$queries}\n");
        $this->assertLessThan(10, $queries, "Submit took {$queries} queries (budget 10)");
    }

    public function test_hundred_simultaneous_submits_complete_in_budget(): void
    {
        $sessions = [];
        for ($i = 0; $i < 100; $i++) {
            $student = User::factory()->create(['role' => 'student']);
            $session = ExamSession::create([
                'exam_id' => $this->exam->id,
                'student_id' => $student->id,
                'teacher_id' => $this->exam->teacher_id,
                'status' => 'in_progress',
                'started_at' => now(),
                'total_questions' => 2,
            ]);
            foreach ($this->questionIds as $qid) {
                \App\Models\StudentAnswer::create([
                    'exam_session_id' => $session->id,
                    'question_id' => $qid,
                    'exam_id' => $this->exam->id,
                    'max_points' => 5,
                    'answer' => ['B'],
                    'is_answered' => true,
                ]);
            }
            $sessions[] = [$student, $session];
        }

        $started = microtime(true);
        foreach ($sessions as [$student, $session]) {
            $this->actingAs($student)
                ->postJson(route('exam.session.submit', $session))
                ->assertOk();
        }
        $elapsed = microtime(true) - $started;

        fwrite(STDERR, sprintf("\n[perf] 100 submits: %.2fs\n", $elapsed));

        $this->assertEquals(100, ExamSession::where('status', 'completed')->count());
        $this->assertLessThan(30, $elapsed, "100 submits took {$elapsed}s (budget 30s)");
    }
}
