<?php

namespace Tests\Unit;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use App\Services\GradingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeSessionWithAnswers(array $specs): ExamSession
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $subject = Subject::factory()->create(['created_by' => $teacher->id]);
        $exam = Exam::factory()->create([
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'status' => 'published',
            'available_from' => null,
            'available_to' => null,
        ]);

        $session = ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'total_questions' => count($specs),
        ]);

        foreach ($specs as $spec) {
            $question = Question::factory()->create(array_merge([
                'subject_id' => $subject->id,
                'created_by' => $teacher->id,
            ], $spec['question']));

            StudentAnswer::create([
                'exam_session_id' => $session->id,
                'question_id' => $question->id,
                'exam_id' => $exam->id,
                'answer' => $spec['answer'],
                'is_answered' => $spec['answer'] !== null,
                'max_points' => $spec['max_points'] ?? 5,
            ]);
        }

        return $session->fresh();
    }

    public function test_correct_mcq_single_earns_full_points(): void
    {
        $session = $this->makeSessionWithAnswers([[
            'question' => [
                'question_type' => 'mcq_single',
                'options' => ['A' => 'One', 'B' => 'Two'],
                'correct_answers' => ['B'],
            ],
            'answer' => ['B'],
        ]]);

        (new GradingService)->gradeSession($session);

        $answer = $session->answers()->first();
        $this->assertTrue((bool) $answer->is_correct);
        $this->assertEquals(5, (float) $answer->points_earned);
    }

    public function test_wrong_and_unanswered_earn_zero(): void
    {
        $session = $this->makeSessionWithAnswers([
            [
                'question' => [
                    'question_type' => 'mcq_single',
                    'options' => ['A' => 'One', 'B' => 'Two'],
                    'correct_answers' => ['B'],
                ],
                'answer' => ['A'],
            ],
            [
                'question' => [
                    'question_type' => 'fill_blank',
                    'correct_answers' => ['paris'],
                ],
                'answer' => null,
            ],
        ]);

        (new GradingService)->gradeSession($session);

        $this->assertEquals(0, $session->answers()->where('is_correct', true)->count());
        $this->assertEquals(0.0, (float) $session->answers()->sum('points_earned'));
    }

    public function test_calculate_score_returns_percentage(): void
    {
        $session = $this->makeSessionWithAnswers([
            [
                'question' => [
                    'question_type' => 'true_false',
                    'correct_answers' => ['true'],
                ],
                'answer' => ['true'],
                'max_points' => 4,
            ],
            [
                'question' => [
                    'question_type' => 'fill_blank',
                    'correct_answers' => ['paris'],
                ],
                'answer' => ['london'],
                'max_points' => 6,
            ],
        ]);

        $service = new GradingService;
        $service->gradeSession($session);
        $score = $service->calculateScore($session);

        $this->assertEquals(4.0, $score['earned']);
        $this->assertEquals(10.0, $score['possible']);
        $this->assertEquals(40.0, $score['percentage']);
    }

    public function test_calculate_score_avoids_division_by_zero(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $exam = Exam::factory()->create(['teacher_id' => $teacher->id, 'status' => 'published']);
        $session = ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'total_questions' => 0,
        ]);

        $score = (new GradingService)->calculateScore($session);

        $this->assertEquals(0.0, $score['percentage']);
    }
}
