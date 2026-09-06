<?php

namespace Tests\Unit;

use App\Data\ExamData;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\ExamManagementService;
use App\Services\FileService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExamManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Subject $subject;

    private Exam $exam;

    private ExamManagementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->subject = Subject::factory()->create(['created_by' => $this->teacher->id]);
        $this->exam = Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'status' => 'draft',
            'total_marks' => 0,
        ]);
        $this->service = new ExamManagementService(new FileService);
    }

    private function makeQuestion(?int $subjectId = null, int $points = 5): Question
    {
        return Question::factory()->create([
            'subject_id' => $subjectId ?? $this->subject->id,
            'created_by' => $this->teacher->id,
            'points' => $points,
        ]);
    }

    private function examData(array $overrides = []): ExamData
    {
        return new ExamData(...array_merge([
            'subject_id' => $this->subject->id,
            'title' => 'Midterm',
            'description' => null,
            'instructions' => null,
            'academic_year' => (int) date('Y'),
            'semester' => 3,
            'status' => 'draft',
            'time_limit' => 60,
            'passing_marks' => 40,
            'max_attempts' => 1,
            'available_from' => null,
            'available_to' => null,
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'teacher_id' => $this->teacher->id,
        ], $overrides));
    }

    public function test_attach_adds_question_with_next_order_and_recalcs(): void
    {
        $q1 = $this->makeQuestion();
        $q2 = $this->makeQuestion(points: 10);

        $this->service->attachQuestion($this->exam, $q1->id, null);
        $this->service->attachQuestion($this->exam, $q2->id, 7.5);

        $this->assertEquals([1, 2], $this->exam->questions()->orderBy('order_index')->pluck('order_index')->toArray());
        $this->assertEquals(12.5, $this->exam->fresh()->total_marks);
    }

    public function test_attach_rejects_duplicate(): void
    {
        $q = $this->makeQuestion();
        $this->service->attachQuestion($this->exam, $q->id, null);

        $this->expectException(DomainException::class);
        $this->service->attachQuestion($this->exam, $q->id, null);
    }

    public function test_attach_rejects_foreign_subject_question(): void
    {
        $other = Subject::factory()->create(['created_by' => $this->teacher->id]);
        $q = $this->makeQuestion($other->id);

        $this->expectException(DomainException::class);
        $this->service->attachQuestion($this->exam, $q->id, null);
    }

    public function test_detach_removes_and_resequences(): void
    {
        $q1 = $this->makeQuestion();
        $q2 = $this->makeQuestion();
        $this->service->attachQuestion($this->exam, $q1->id, null);
        $this->service->attachQuestion($this->exam, $q2->id, null);

        $this->service->detachQuestion($this->exam, $q1->id);

        $this->assertEquals([1], $this->exam->questions()->orderBy('order_index')->pluck('order_index')->toArray());
        $this->assertEquals(5, $this->exam->fresh()->total_marks);
    }

    public function test_detach_rejects_unattached_question(): void
    {
        $q = $this->makeQuestion();

        $this->expectException(DomainException::class);
        $this->service->detachQuestion($this->exam, $q->id);
    }

    public function test_reorder_rewrites_order_index(): void
    {
        $q1 = $this->makeQuestion();
        $q2 = $this->makeQuestion();
        $this->service->attachQuestion($this->exam, $q1->id, null);
        $this->service->attachQuestion($this->exam, $q2->id, null);

        $this->service->reorderQuestions($this->exam, [
            ['id' => $q2->id, 'order' => 1],
            ['id' => $q1->id, 'order' => 2],
        ]);

        $this->assertEquals(
            [$q2->id, $q1->id],
            $this->exam->questions()->orderBy('order_index')->pluck('question_id')->toArray()
        );
    }

    public function test_reorder_rejects_mismatched_set(): void
    {
        $q1 = $this->makeQuestion();
        $this->service->attachQuestion($this->exam, $q1->id, null);

        $this->expectException(DomainException::class);
        $this->service->reorderQuestions($this->exam, [
            ['id' => $q1->id, 'order' => 1],
            ['id' => 999999, 'order' => 2],
        ]);
    }

    public function test_update_points_recalcs_total(): void
    {
        $q = $this->makeQuestion(points: 5);
        $this->service->attachQuestion($this->exam, $q->id, null);

        $this->service->updateQuestionPoints($this->exam, $q->id, 12);

        $this->assertEquals(12, $this->exam->fresh()->total_marks);
    }

    public function test_bulk_add_attaches_subject_questions(): void
    {
        $q1 = $this->makeQuestion();
        $q2 = $this->makeQuestion();

        $count = $this->service->bulkAddQuestions($this->exam, [$q1->id, $q2->id]);

        $this->assertEquals(2, $count);
        $this->assertEquals(10, $this->exam->fresh()->total_marks);
    }

    public function test_bulk_add_rejects_foreign_subject(): void
    {
        $other = Subject::factory()->create(['created_by' => $this->teacher->id]);
        $bad = $this->makeQuestion($other->id);

        $this->expectException(DomainException::class);
        $this->service->bulkAddQuestions($this->exam, [$bad->id]);
    }

    public function test_create_stores_upload_and_sets_teacher(): void
    {
        $file = UploadedFile::fake()->create('instructions.pdf', 100, 'application/pdf');

        $exam = $this->service->createExam($this->examData(), $file);

        $this->assertEquals($this->teacher->id, $exam->teacher_id);
        $this->assertNotNull($exam->instructions_file);
        Storage::disk('public')->assertExists($exam->instructions_file);
    }

    public function test_update_replaces_upload_and_deletes_old(): void
    {
        $exam = $this->service->createExam(
            $this->examData(),
            UploadedFile::fake()->create('old.pdf', 100, 'application/pdf')
        );
        $oldPath = $exam->instructions_file;

        $this->service->updateExam(
            $exam,
            $this->examData(['title' => 'Final']),
            UploadedFile::fake()->create('new.pdf', 100, 'application/pdf')
        );

        $this->assertEquals('Final', $exam->fresh()->title);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($exam->fresh()->instructions_file);
    }

    public function test_delete_removes_upload_and_detaches(): void
    {
        $q = $this->makeQuestion();
        $exam = $this->service->createExam(
            $this->examData(),
            UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')
        );
        $this->service->attachQuestion($exam, $q->id, null);
        $path = $exam->instructions_file;

        $this->service->deleteExam($exam->fresh());

        $this->assertNull(Exam::find($exam->id));
        Storage::disk('public')->assertMissing($path);
    }

    public function test_delete_refuses_exam_with_started_sessions(): void
    {
        $student = User::factory()->create(['role' => 'student']);
        \App\Models\ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $student->id,
            'teacher_id' => $this->teacher->id,
            'status' => 'completed',
            'total_questions' => 0,
        ]);

        $this->expectException(DomainException::class);
        $this->service->deleteExam($this->exam);
    }

    public function test_stats_groups_by_question_type(): void
    {
        $this->service->attachQuestion($this->exam, $this->makeQuestion()->id, null);

        $stats = $this->service->examStats($this->exam);

        $this->assertEquals(1, $stats['total_questions']);
        $this->assertArrayHasKey('mcq_single_count', $stats);
        $this->assertArrayHasKey('fill_blank_count', $stats);
    }

    public function test_listing_scopes_to_teacher_and_filters(): void
    {
        $other = User::factory()->create(['role' => 'teacher']);
        Exam::factory()->create([
            'subject_id' => $this->subject->id, 'teacher_id' => $other->id, 'status' => 'draft',
        ]);

        $mine = $this->service->paginateFor($this->teacher, ['per_page' => 10]);

        $this->assertEquals(1, $mine->total());
        $this->assertEquals($this->teacher->id, $mine->first()->teacher_id);
    }
}
