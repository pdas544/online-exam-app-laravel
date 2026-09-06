<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Dashboard read models: student/teacher/admin overviews, results shaping,
 * and the admin active-session list. Heavy lists use withCount/withSum and
 * the student availability list is cached (short TTL, safe to go stale).
 */
class DashboardService
{
    /**
     * @return array{resumeExams: array, availableExams: array}
     */
    public function studentOverview(int $studentId): array
    {
        return [
            'resumeExams' => $this->resumeExams($studentId),
            'availableExams' => $this->availableExams($studentId),
        ];
    }

    /**
     * @return array<int, array>
     */
    public function resumeExams(int $studentId): array
    {
        return ExamSession::with(['exam.subject', 'exam.teacher'])
            ->where('student_id', $studentId)
            ->where('status', 'in_progress')
            ->get()
            ->map(function ($session) {
                $exam = $session->exam;
                $answered = $session->answers()->where('is_answered', true)->count();
                $total = $session->total_questions;
                $percentage = $total > 0 ? round(($answered / $total) * 100) : 0;

                return [
                    'session_id' => $session->id,
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'subject' => $exam->subject->name ?? 'N/A',
                    'teacher' => $exam->teacher->name ?? 'N/A',
                    'duration' => $exam->time_limit,
                    'total_marks' => $exam->total_marks,
                    'questions_count' => $exam->questions()->count(),
                    'progress' => $answered.'/'.$total,
                    'percentage' => $percentage,
                    'time_spent' => $session->time_spent,
                    'available_until' => $exam->available_to
                        ? $exam->available_to->format('M d, Y h:i A') : 'No deadline',
                ];
            })->toArray();
    }

    /**
     * @return array<int, array>
     */
    public function availableExams(int $studentId): array
    {
        return Cache::remember(
            "dashboard:student:{$studentId}:available",
            300,
            function () use ($studentId) {
                $excludeIds = array_merge(
                    $this->inProgressExamIds($studentId),
                    $this->maxAttemptsReachedExamIds($studentId)
                );

                $query = Exam::with(['subject', 'teacher'])
                    ->where('status', 'published')
                    ->where(function ($q) {
                        $q->whereNull('available_from')
                            ->orWhere('available_from', '<=', now());
                    })
                    ->where(function ($q) {
                        $q->whereNull('available_to')
                            ->orWhere('available_to', '>=', now());
                    });

                if (! empty($excludeIds)) {
                    $query->whereNotIn('id', $excludeIds);
                }

                return $query->withCount('questions')
                    ->orderBy('updated_at', 'desc')
                    ->get()
                    ->map(function ($exam) {
                        return [
                            'id' => $exam->id,
                            'title' => $exam->title,
                            'subject' => $exam->subject->name ?? 'N/A',
                            'teacher' => $exam->teacher->name ?? 'N/A',
                            'duration' => $exam->time_limit,
                            'total_marks' => $exam->total_marks,
                            'questions_count' => $exam->questions_count,
                            'available_until' => $exam->available_to
                                ? $exam->available_to->format('M d, Y h:i A') : 'No deadline',
                        ];
                    })->toArray();
            }
        );
    }

    /**
     * @return array<int, array>
     */
    public function studentResults(int $studentId): array
    {
        return ExamSession::with('exam')
            ->where('student_id', $studentId)
            ->where('status', 'completed')
            ->orderByDesc('submitted_at')
            ->withSum('answers as marks_secured', 'points_earned')
            ->withSum('answers as total_marks', 'max_points')
            ->get()
            ->map(function ($session) {
                return [
                    'session_id' => $session->id,
                    'exam_name' => $session->exam->title ?? 'N/A',
                    'marks_secured' => (float) ($session->marks_secured ?? 0),
                    'total_marks' => (float) ($session->total_marks ?? 0),
                    'submitted_at' => optional($session->submitted_at)->format('M d, Y h:i A'),
                ];
            })->toArray();
    }

    /**
     * @return array{summary: array, rows: array}
     */
    public function studentResultDetail(ExamSession $session): array
    {
        $session->loadMissing(['exam.subject', 'answers.question']);

        $rows = $session->answers
            ->sortBy('question_id')
            ->values()
            ->map(function ($answer, $index) {
                $question = $answer->question;

                return [
                    'index' => $index + 1,
                    'question_text' => $question->question_text ?? 'N/A',
                    'correct_option' => $this->formatAnswerForDisplay($question, $question->correct_answers),
                    'selected_option' => $this->formatAnswerForDisplay($question, $answer->answer),
                    'is_correct' => (bool) $answer->is_correct,
                ];
            })->toArray();

        $summary = [
            'exam_name' => $session->exam->title ?? 'N/A',
            'subject' => $session->exam->subject->name ?? 'N/A',
            'submitted_at' => optional($session->submitted_at)->format('M d, Y h:i A'),
            'marks_secured' => (float) $session->answers->sum('points_earned'),
            'total_marks' => (float) $session->answers->sum('max_points'),
        ];

        return ['summary' => $summary, 'rows' => $rows];
    }

    public function formatAnswerForDisplay(?Question $question, mixed $rawAnswer): string
    {
        $values = $this->normalizeAnswerValues($rawAnswer);

        if (empty($values)) {
            return 'Not answered';
        }

        $display = [];
        foreach ($values as $value) {
            $display[] = $this->formatSingleAnswerValue($question, $value);
        }

        return implode(', ', $display);
    }

    private function normalizeAnswerValues(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter($value, static function ($item) {
                return $item !== null && $item !== '';
            }));
        }

        return [$value];
    }

    private function formatSingleAnswerValue(?Question $question, mixed $value): string
    {
        $stringValue = is_string($value) ? $value : (string) $value;

        if (! $question) {
            return $stringValue;
        }

        if (in_array($question->question_type, ['mcq_single', 'mcq_multiple'], true)) {
            $optionText = is_array($question->options) ? ($question->options[$stringValue] ?? null) : null;

            return $optionText ? ($stringValue.'. '.$optionText) : $stringValue;
        }

        if ($question->question_type === 'true_false') {
            return ucfirst($stringValue);
        }

        return $stringValue;
    }

    /**
     * @return array{upcomingExams: array}
     */
    public function teacherOverview(int $teacherId): array
    {
        $upcoming = Exam::where('teacher_id', $teacherId)
            ->where('status', 'published')
            ->where(function ($query) {
                $query->whereNull('available_to')
                    ->orWhere('available_to', '>=', now());
            })
            ->withCount('questions')
            ->orderBy('available_from', 'asc')
            ->take(5)
            ->get()
            ->map(function ($exam) {
                return [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'subject' => $exam->subject->name ?? 'N/A',
                    'question_count' => $exam->questions_count,
                    'total_marks' => $exam->total_marks,
                    'time_limit' => $exam->time_limit,
                    'available_from' => $exam->available_from
                        ? $exam->available_from->format('M d, Y h:i A') : 'Anytime',
                    'status' => $exam->isAvailable() ? 'available' : 'upcoming',
                ];
            })->toArray();

        return ['upcomingExams' => $upcoming];
    }

    /**
     * @return array{stats: array, quickActions: array, recentActivity: array}
     */
    public function adminOverview(): array
    {
        $stats = [
            'total_users' => User::count(),
            'total_students' => User::where('role', 'student')->count(),
            'total_teachers' => User::where('role', 'teacher')->count(),
            'total_admins' => User::where('role', 'admin')->count(),
            'total_subjects' => Subject::count(),
            'total_questions' => Question::count(),
            'total_exams' => Exam::count(),
            'active_exams' => Exam::where('status', 'published')->count(),
            'active_exam_sessions' => ExamSession::active()->count(),
            'recent_users' => User::latest()->take(5)->get(),
        ];

        return [
            'stats' => $stats,
            'quickActions' => $this->adminQuickActions(),
            'recentActivity' => $this->adminRecentActivity(),
        ];
    }

    private function adminQuickActions(): array
    {
        return [
            ['label' => 'Manage Users', 'route' => route('users.index'), 'icon' => 'bi-people', 'color' => 'primary', 'width' => 3],
            ['label' => 'Manage Subjects', 'route' => route('subjects.index'), 'icon' => 'bi-book', 'color' => 'info', 'width' => 3],
            ['label' => 'Manage Questions', 'route' => route('questions.index'), 'icon' => 'bi-question-circle', 'color' => 'warning', 'width' => 3],
            ['label' => 'Manage Exams', 'route' => route('exams.index'), 'icon' => 'bi-file-text', 'color' => 'danger', 'width' => 3],
            ['label' => 'Add New User', 'route' => route('users.create'), 'icon' => 'bi-person-plus', 'color' => 'success', 'width' => 2],
            ['label' => 'System Reports', 'route' => '#', 'icon' => 'bi-bar-chart', 'color' => 'secondary', 'width' => 2],
        ];
    }

    private function adminRecentActivity(): array
    {
        return [
            ['title' => 'New User Registered', 'description' => 'John Doe created a teacher account', 'time' => '5 minutes ago'],
            ['title' => 'Exam Published', 'description' => 'Mathematics Final Exam was published', 'time' => '1 hour ago'],
        ];
    }

    /**
     * @return array{sessions: \Illuminate\Contracts\Pagination\LengthAwarePaginator, counts: array}
     */
    public function activeSessions(?string $statusFilter, int $perPage = 12): array
    {
        $allowed = ['in_progress', 'paused'];

        $query = ExamSession::with(['exam:id,title', 'student:id,name,email', 'teacher:id,name'])
            ->whereIn('status', $allowed);

        if (in_array($statusFilter, $allowed, true)) {
            $query->where('status', $statusFilter);
        }

        return [
            'sessions' => $query->latest('started_at')->paginate($perPage)->withQueryString(),
            'counts' => [
                'in_progress' => ExamSession::where('status', 'in_progress')->count(),
                'paused' => ExamSession::where('status', 'paused')->count(),
            ],
        ];
    }

    /**
     * @return array<int>
     */
    private function inProgressExamIds(int $studentId): array
    {
        return ExamSession::where('student_id', $studentId)
            ->where('status', 'in_progress')
            ->pluck('exam_id')
            ->toArray();
    }

    /**
     * Exams whose completed attempts reached max_attempts.
     *
     * @return array<int>
     */
    private function maxAttemptsReachedExamIds(int $studentId): array
    {
        $counts = ExamSession::where('student_id', $studentId)
            ->where('status', 'completed')
            ->selectRaw('exam_id, COUNT(*) as attempts')
            ->groupBy('exam_id')
            ->pluck('attempts', 'exam_id');

        if ($counts->isEmpty()) {
            return [];
        }

        $limits = Exam::whereIn('id', $counts->keys())->pluck('max_attempts', 'id');

        return $counts->filter(
            fn ($attempts, $examId) => $attempts >= ($limits[$examId] ?? 1)
        )->keys()->map(fn ($id) => (int) $id)->toArray();
    }
}
