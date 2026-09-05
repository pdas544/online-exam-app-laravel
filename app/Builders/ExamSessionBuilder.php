<?php

namespace App\Builders;

use App\Models\ExamSession;
use Illuminate\Database\Eloquent\Builder;

class ExamSessionBuilder
{
    private Builder $query;

    public function __construct()
    {
        $this->query = ExamSession::query();
    }

    public function forExam(int $examId): self
    {
        $this->query->where('exam_id', $examId);
        return $this;
    }

    public function forStudent(int $studentId): self
    {
        $this->query->where('student_id', $studentId);
        return $this;
    }

    public function forTeacher(int $teacherId): self
    {
        $this->query->where('teacher_id', $teacherId);
        return $this;
    }

    public function statuses(array $statuses): self
    {
        $this->query->whereIn('status', $statuses);
        return $this;
    }

    public function active(): self
    {
        return $this->statuses(['scheduled', 'in_progress', 'paused']);
    }

    public function withStudentDetails(): self
    {
        $this->query->with(['student' => function ($query) {
            $query->select('id', 'name', 'email');
        }]);

        return $this;
    }

    public function withAnsweredCount(): self
    {
        $this->query->withCount([
            'answers as answered_answers_count' => function ($query) {
                $query->where('is_answered', true);
            },
        ]);

        return $this;
    }

    public function selectEssentialColumns(bool $includeStartedAt = true): self
    {
        $columns = [
            'id',
            'exam_id',
            'student_id',
            'status',
            'violation_count',
            'total_questions',
            'time_spent',
            'last_activity_at',
            'updated_at',
            'created_at',
        ];

        if ($includeStartedAt) {
            $columns[] = 'started_at';
        }

        $this->query->select($columns);

        return $this;
    }

    public function latestBy(string $column = 'updated_at'): self
    {
        $this->query->latest($column);
        return $this;
    }

    public function get()
    {
        return $this->query->get();
    }

    public function paginate(int $perPage = 15)
    {
        return $this->query->paginate($perPage);
    }
}
