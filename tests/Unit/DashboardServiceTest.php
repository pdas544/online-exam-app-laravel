<?php

namespace Tests\Unit;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Subject $subject;

    private Exam $exam;

    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->student = User::factory()->create(['role' => 'student']);
        $this->subject = Subject::factory()->create(['created_by' => $this->teacher->id]);
        $this->exam = Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'status' => 'published',
            'available_from' => null,
            'available_to' => null,
            'max_attempts' => 1,
            'time_limit' => 60,
        ]);
        $question = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacher->id,
            'points' => 5,
        ]);
        $this->exam->questions()->attach($question->id, ['order_index' => 1]);
        $this->service = new DashboardService;
    }

    public function test_student_overview_lists_available_exam_with_counts(): void
    {
        $overview = $this->service->studentOverview($this->student->id);

        $this->assertCount(1, $overview['availableExams']);
        $this->assertEquals($this->exam->id, $overview['availableExams'][0]['id']);
        $this->assertEquals(1, $overview['availableExams'][0]['questions_count']);
        $this->assertEquals([], $overview['resumeExams']);
    }

    public function test_student_overview_hides_maxed_out_exam_and_caches(): void
    {
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => 'completed',
            'submitted_at' => now(),
            'total_questions' => 1,
        ]);

        $overview = $this->service->studentOverview($this->student->id);

        $this->assertEquals([], $overview['availableExams']);
        $this->assertTrue(Cache::has("dashboard:student:{$this->student->id}:available"));
    }

    public function test_student_overview_lists_resumable_session(): void
    {
        ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'total_questions' => 1,
        ]);

        $overview = $this->service->studentOverview($this->student->id);

        $this->assertEquals([], $overview['availableExams']);
        $this->assertCount(1, $overview['resumeExams']);
        $this->assertEquals('0/1', $overview['resumeExams'][0]['progress']);
    }

    public function test_student_results_uses_stored_score_without_per_session_sums(): void
    {
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => 'completed',
            'submitted_at' => now(),
            'score' => 80,
            'total_questions' => 1,
        ]);
        StudentAnswer::create([
            'exam_session_id' => $session->id,
            'question_id' => $this->exam->questions()->first()->id,
            'exam_id' => $this->exam->id,
            'max_points' => 5,
            'points_earned' => 4,
            'is_answered' => true,
            'is_correct' => true,
        ]);

        $results = $this->service->studentResults($this->student->id);

        $this->assertCount(1, $results);
        $this->assertEquals(4.0, (float) $results[0]['marks_secured']);
        $this->assertEquals(5.0, (float) $results[0]['total_marks']);
    }

    public function test_student_result_detail_shapes_rows_and_summary(): void
    {
        $session = ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => 'completed',
            'submitted_at' => now(),
            'total_questions' => 1,
        ]);
        StudentAnswer::create([
            'exam_session_id' => $session->id,
            'question_id' => $this->exam->questions()->first()->id,
            'exam_id' => $this->exam->id,
            'max_points' => 5,
            'points_earned' => 5,
            'is_answered' => true,
            'is_correct' => true,
            'answer' => ['A'],
        ]);

        $detail = $this->service->studentResultDetail($session->fresh());

        $this->assertEquals($this->exam->title, $detail['summary']['exam_name']);
        $this->assertCount(1, $detail['rows']);
        $this->assertTrue($detail['rows'][0]['is_correct']);
    }

    public function test_teacher_overview_lists_upcoming_exams(): void
    {
        $overview = $this->service->teacherOverview($this->teacher->id);

        $this->assertCount(1, $overview['upcomingExams']);
        $this->assertEquals(1, $overview['upcomingExams'][0]['question_count']);
        $this->assertEquals('available', $overview['upcomingExams'][0]['status']);
    }

    public function test_admin_overview_counts_entities(): void
    {
        $overview = $this->service->adminOverview();

        $this->assertEquals(1, $overview['stats']['total_students']);
        $this->assertEquals(1, $overview['stats']['total_teachers']);
        $this->assertEquals(1, $overview['stats']['total_exams']);
        $this->assertEquals(1, $overview['stats']['active_exams']);
        $this->assertNotEmpty($overview['quickActions']);
    }

    public function test_active_sessions_paginates_with_column_selection(): void
    {
        ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'total_questions' => 1,
        ]);

        $result = $this->service->activeSessions('in_progress', 12);

        $this->assertEquals(1, $result['sessions']->total());
        $this->assertEquals(1, $result['counts']['in_progress']);
    }
}
