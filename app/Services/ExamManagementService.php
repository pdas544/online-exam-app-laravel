<?php

namespace App\Services;

use App\Data\ExamData;
use App\Models\Exam;
use App\Models\Question;
use App\Models\User;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Exam content management: listing, CRUD, and question-pivot mutations.
 * Every mutation runs in its own transaction and recalculates total marks.
 */
class ExamManagementService
{
    public function __construct(private FileService $files) {}

    /**
     * Teacher-scoped, filterable exam listing.
     */
    public function paginateFor(User $user, array $filters): LengthAwarePaginator
    {
        $query = Exam::with(['subject', 'teacher']);

        if (! empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! $user->isAdmin()) {
            $query->where('teacher_id', $user->id);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['academic_year'])) {
            $query->where('academic_year', $filters['academic_year']);
        }

        if (! empty($filters['semester'])) {
            $query->where('semester', $filters['semester']);
        }

        return $query->withCount('questions')->latest()->paginate($filters['per_page'] ?? 10);
    }

    public function createExam(ExamData $data, ?UploadedFile $file): Exam
    {
        $attributes = $data->toAttributes();
        $attributes['instructions_file'] = $this->files->store($file);
        $attributes['total_marks'] = 0;

        return Exam::create($attributes);
    }

    public function updateExam(Exam $exam, ExamData $data, ?UploadedFile $file): Exam
    {
        $attributes = $data->toAttributes();
        unset($attributes['teacher_id']);
        $attributes['instructions_file'] = $this->files->replace($exam->instructions_file, $file);

        $exam->update($attributes);

        return $exam->fresh();
    }

    /**
     * @throws DomainException when students have started the exam.
     */
    public function deleteExam(Exam $exam): void
    {
        if ($exam->sessions()->whereIn('status', ['completed', 'in_progress'])->count() > 0) {
            throw new DomainException('Cannot delete exam that has been started by students.');
        }

        DB::transaction(function () use ($exam) {
            $exam->questions()->detach();
            $this->files->delete($exam->instructions_file);
            $exam->delete();
        });
    }

    /**
     * @throws DomainException on duplicate or foreign-subject question.
     */
    public function attachQuestion(Exam $exam, int $questionId, float|int|null $pointsOverride): void
    {
        $question = Question::findOrFail($questionId);

        if ($question->subject_id !== $exam->subject_id) {
            throw new DomainException('Question does not belong to this exam\'s subject.');
        }

        if ($exam->questions()->where('question_id', $questionId)->exists()) {
            throw new DomainException('Question already exists in this exam.');
        }

        DB::transaction(function () use ($exam, $questionId, $pointsOverride) {
            $nextOrder = ($exam->questions()->max('order_index') ?? 0) + 1;

            $exam->questions()->attach($questionId, [
                'order_index' => $nextOrder,
                'points_override' => $pointsOverride,
            ]);

            $exam->updateTotalMarks();
        });
    }

    /**
     * @throws DomainException when the question is not attached to this exam.
     */
    public function detachQuestion(Exam $exam, int $questionId): void
    {
        $this->requireAttached($exam, $questionId);

        DB::transaction(function () use ($exam, $questionId) {
            $exam->questions()->detach($questionId);

            $remaining = $exam->questions()->orderBy('order_index')->get();
            foreach ($remaining as $index => $question) {
                $exam->questions()->updateExistingPivot($question->id, ['order_index' => $index + 1]);
            }

            $exam->updateTotalMarks();
        });
    }

    /**
     * @param  array<int, array{id: int, order: int}>  $items
     *
     * @throws DomainException when the set does not match the exam's questions.
     */
    public function reorderQuestions(Exam $exam, array $items): void
    {
        $requested = collect($items)->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->toArray();
        $attached = $exam->questions()->pluck('question_id')->map(fn ($id) => (int) $id)->sort()->values()->toArray();

        if ($requested !== $attached) {
            throw new DomainException('Question set does not match this exam.');
        }

        DB::transaction(function () use ($exam, $items) {
            foreach ($items as $item) {
                $exam->questions()->updateExistingPivot($item['id'], ['order_index' => $item['order']]);
            }
        });
    }

    /**
     * @throws DomainException when the question is not attached to this exam.
     */
    public function updateQuestionPoints(Exam $exam, int $questionId, float|int $points): void
    {
        $this->requireAttached($exam, $questionId);

        DB::transaction(function () use ($exam, $questionId, $points) {
            $exam->questions()->updateExistingPivot($questionId, ['points_override' => $points]);

            $exam->updateTotalMarks();
        });
    }

    /**
     * @return int number of questions attached.
     *
     * @throws DomainException when any question is outside the exam's subject.
     */
    public function bulkAddQuestions(Exam $exam, array $questionIds): int
    {
        $current = $exam->questions->pluck('id')->toArray();
        $new = array_values(array_diff($questionIds, $current));

        if (empty($new)) {
            return 0;
        }

        $mismatched = Question::whereIn('id', $new)
            ->where('subject_id', '!=', $exam->subject_id)
            ->count();

        if ($mismatched > 0) {
            throw new DomainException('Some questions do not belong to this exam\'s subject.');
        }

        return DB::transaction(function () use ($exam, $new) {
            $nextOrder = $exam->questions()->max('order_index') ?? 0;

            foreach ($new as $questionId) {
                $nextOrder++;
                $exam->questions()->attach($questionId, ['order_index' => $nextOrder]);
            }

            $exam->updateTotalMarks();

            return count($new);
        });
    }

    /**
     * Type breakdown for the exam detail view.
     */
    public function examStats(Exam $exam): array
    {
        $byType = $exam->questions->groupBy('question_type');

        return [
            'total_questions' => $exam->questions->count(),
            'total_marks' => $exam->total_marks,
            'mcq_single_count' => $byType->get('mcq_single', collect())->count(),
            'mcq_multiple_count' => $byType->get('mcq_multiple', collect())->count(),
            'true_false_count' => $byType->get('true_false', collect())->count(),
            'fill_blank_count' => $byType->get('fill_blank', collect())->count(),
        ];
    }

    /**
     * Question-bank lists: attached (ordered) + available via subquery.
     *
     * @return array{attached: \Illuminate\Database\Eloquent\Collection, available: LengthAwarePaginator, all: LengthAwarePaginator}
     */
    public function manageLists(Exam $exam): array
    {
        $attached = $exam->questions()->orderBy('exam_questions.order_index')->get();

        $available = Question::where('subject_id', $exam->subject_id)
            ->whereDoesntHave('exams', fn ($q) => $q->where('exams.id', $exam->id))
            ->orderBy('question_type')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $all = Question::where('subject_id', $exam->subject_id)
            ->orderBy('question_type')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return ['attached' => $attached, 'available' => $available, 'all' => $all];
    }

    /**
     * @throws DomainException when the question is not attached to this exam.
     */
    private function requireAttached(Exam $exam, int $questionId): void
    {
        if (! $exam->questions()->where('question_id', $questionId)->exists()) {
            throw new DomainException('Question is not part of this exam.');
        }
    }
}
