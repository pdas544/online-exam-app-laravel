<?php

namespace Tests\Unit;

use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\ExamManagementService;
use App\Services\ExamService;
use App\Services\FileService;
use App\Services\SubjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExamCacheTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Exam $exam;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->subject = Subject::factory()->create(['created_by' => $this->teacher->id]);
        $this->exam = Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'status' => 'published',
            'available_from' => null,
            'available_to' => null,
        ]);
        Question::factory()->count(2)->create([
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacher->id,
        ]);
        $this->exam->questions()->attach(
            Question::where('subject_id', $this->subject->id)->pluck('id')->mapWithKeys(
                fn ($id, $i) => [$id => ['order_index' => $i + 1]]
            )->toArray()
        );
    }

    private function countQueries(callable $fn): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        $fn();

        return $count;
    }

    public function test_exam_paper_is_cached_on_second_read(): void
    {
        $papers = new ExamService;

        $papers->getExamWithQuestions($this->exam);
        $freshExam = $this->exam->fresh();
        $secondReadQueries = $this->countQueries(
            fn () => $papers->getExamWithQuestions($freshExam)
        );

        $this->assertEquals(0, $secondReadQueries);
    }

    public function test_question_mutation_busts_exam_paper_cache(): void
    {
        $papers = new ExamService;
        $papers->getExamWithQuestions($this->exam);

        $extra = Question::factory()->create([
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacher->id,
        ]);
        (new ExamManagementService(new FileService, $papers))
            ->attachQuestion($this->exam, $extra->id, null);

        $freshExam = $this->exam->fresh();
        $afterMutationQueries = $this->countQueries(
            fn () => $papers->getExamWithQuestions($freshExam)
        );

        // Miss re-runs subject + teacher + questions reads (3), not just the
        // 0-query hit — proving the mutation actually busted the entry.
        $this->assertGreaterThanOrEqual(3, $afterMutationQueries);
    }

    public function test_subject_mutation_busts_subjects_cache(): void
    {
        $subjects = new SubjectService;
        $subjects->getAllSubjects();
        $this->assertTrue(Cache::has('subjects.all'));

        $this->actingAs($this->teacher)->post(route('subjects.store'), [
            'name' => 'Brand New Subject',
            'description' => 'Cache buster',
        ])->assertRedirect(route('subjects.index'));

        $this->assertFalse(Cache::has('subjects.all'));
    }
}
