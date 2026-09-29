<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $otherTeacher;

    private User $admin;

    private User $student;

    private Exam $exam;

    private Question $question;

    protected function setUp(): void
    {
        parent::setUp();
        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->otherTeacher = User::factory()->create(['role' => 'teacher']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->student = User::factory()->create(['role' => 'student']);
        $subject = Subject::factory()->create(['created_by' => $this->teacher->id]);
        $this->exam = Exam::factory()->create([
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'status' => 'published',
            'available_from' => null,
            'available_to' => null,
            'passing_marks' => 40,
        ]);
        $this->question = Question::factory()->create([
            'subject_id' => $subject->id,
            'created_by' => $this->teacher->id,
            'question_type' => 'mcq_single',
            'options' => ['A' => 'First', 'B' => 'Second'],
            'correct_answers' => ['A'],
            'points' => 5,
        ]);
        $this->exam->questions()->attach($this->question->id, ['order_index' => 1]);
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => 'completed',
            'started_at' => now()->subMinutes(10),
            'submitted_at' => now(),
            'total_questions' => 1,
            'score' => 80,
            'passed' => true,
        ]);
        StudentAnswer::create([
            'exam_session_id' => $session->id,
            'question_id' => $this->question->id,
            'exam_id' => $this->exam->id,
            'max_points' => 5,
            'answer' => ['A'],
            'is_answered' => true,
            'is_correct' => true,
            'points_earned' => 5,
        ]);
    }

    public function test_teacher_can_view_own_report(): void
    {
        $this->actingAs($this->teacher)->get(route('teacher.reports.show', $this->exam))->assertOk()->assertSee($this->exam->title);
    }

    public function test_other_teacher_cannot_view_report(): void
    {
        $this->actingAs($this->otherTeacher)->get(route('teacher.reports.show', $this->exam))->assertForbidden();
    }

    public function test_admin_can_view_any_report(): void
    {
        $this->actingAs($this->admin)->get(route('teacher.reports.show', $this->exam))->assertOk();
    }

    public function test_student_cannot_view_report(): void
    {
        $this->actingAs($this->student)->get(route('teacher.reports.show', $this->exam))->assertForbidden();
    }

    public function test_csv_register_returns_csv(): void
    {
        $res = $this->actingAs($this->teacher)->get(route('teacher.reports.csv.register', $this->exam));
        $res->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('.csv', $res->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Student', $res->streamedContent());
    }

    public function test_pdf_register_returns_pdf(): void
    {
        $res = $this->actingAs($this->teacher)->get(route('teacher.reports.pdf.register', $this->exam));
        $res->assertOk();
        $this->assertStringContainsString('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }

    public function test_not_attempted_distinct_from_wrong(): void
    {
        $s2 = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => User::factory()->create(['role' => 'student'])->id,
            'teacher_id' => $this->teacher->id,
            'status' => 'completed',
            'started_at' => now()->subMinutes(5),
            'submitted_at' => now(),
            'total_questions' => 1,
            'score' => 0,
            'passed' => false,
        ]);
        StudentAnswer::create([
            'exam_session_id' => $s2->id,
            'question_id' => $this->question->id,
            'exam_id' => $this->exam->id,
            'max_points' => 5,
            'answer' => null,
            'is_answered' => false,
            'is_correct' => false,
            'points_earned' => 0,
        ]);
        $card = app(\App\Services\ReportService::class)->studentCard($s2);
        $this->assertEquals('not_attempted', $card['rows'][0]['status']);
    }
}
