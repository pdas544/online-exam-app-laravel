<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Models\ViolationLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

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
            'pendingGrades' => $this->pendingGradesCount($studentId),
        ];
    }

    /**
     * Completed but ungraded sessions — the student-facing "grading…" state.
     */
    public function pendingGradesCount(int $studentId): int
    {
        return ExamSession::where('student_id', $studentId)
            ->where('status', 'completed')
            ->whereNull('score')
            ->count();
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
                    ->map(function (Exam $exam) {
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
                    'grading_pending' => $session->score === null,
                ];
            })->toArray();
    }

    /**
     * Pending jobs across the queues this app uses. On the Redis driver the
     * DB `jobs` table stays empty, so sum the real queues instead; any Redis
     * outage falls back to the DB count rather than 500ing the dashboard.
     */
    private function queueDepth(): int
    {
        if (config('queue.default') !== 'redis') {
            return DB::table('jobs')->count();
        }

        try {
            $queues = array_unique([
                'grading',
                'violations',
                'broadcasts',
                (string) config('queue.connections.redis.queue', 'default'),
            ]);

            $total = 0;
            foreach ($queues as $queue) {
                $total += Queue::connection('redis')->size($queue);
            }

            return $total;
        } catch (\Throwable) {
            return DB::table('jobs')->count();
        }
    }

    /**
     * @return array{summary: array, rows: array, grading_pending: bool}
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

        return ['summary' => $summary, 'rows' => $rows, 'grading_pending' => $session->score === null];
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
     * @return array{upcomingExams: array, recentExams: array}
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
            ->map(function (Exam $exam) {
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

        // Recently concluded: published exams whose window closed. Without
        // this, a finished exam vanishes from the dashboard entirely the
        // moment available_to passes, which reads as missing data.
        $recent = Exam::where('teacher_id', $teacherId)
            ->where('status', 'published')
            ->whereNotNull('available_to')
            ->where('available_to', '<', now())
            ->withCount('questions')
            ->orderBy('available_to', 'desc')
            ->take(5)
            ->get()
            ->map(function (Exam $exam) {
                return [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'subject' => $exam->subject->name ?? 'N/A',
                    'question_count' => $exam->questions_count,
                    'total_marks' => $exam->total_marks,
                    'time_limit' => $exam->time_limit,
                    'available_to' => $exam->available_to
                        ? $exam->available_to->format('M d, Y h:i A') : '-',
                ];
            })->toArray();

        return ['upcomingExams' => $upcoming, 'recentExams' => $recent];
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

    /**
     * Operational signals shared by the admin dashboard and /admin/metrics.
     *
     * @return array{sessions_by_status: array, queue_depth: int, failed_jobs: int, violations_last_hour: int, ungraded_completions: int, live_exams: int}
     */
    public function adminHealth(): array
    {
        return [
            'sessions_by_status' => ExamSession::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'queue_depth' => $this->queueDepth(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'violations_last_hour' => ViolationLog::where('created_at', '>=', now()->subHour())->count(),
            'ungraded_completions' => ExamSession::where('status', 'completed')
                ->whereNull('score')
                ->count(),
            'live_exams' => Exam::where('status', 'published')
                ->where(function ($query) {
                    $query->whereNull('available_from')
                        ->orWhere('available_from', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('available_to')
                        ->orWhere('available_to', '>=', now());
                })
                ->count(),
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
        $activity = User::latest()->limit(3)
            ->get(['name', 'role', 'created_at'])
            ->map(fn (User $user) => [
                'title' => 'New '.ucfirst((string) $user->role).' Registered',
                'description' => "{$user->name} joined as {$user->role}",
                'time' => $user->created_at->diffForHumans(),
            ])
            ->all();

        $sessions = ExamSession::with('exam:id,title')->latest('started_at')->limit(3)->get();
        foreach ($sessions as $session) {
            $activity[] = [
                'title' => 'Exam '.ucfirst((string) $session->status),
                'description' => ($session->exam->title ?? 'An exam')." — session #{$session->id}",
                'time' => $session->started_at?->diffForHumans() ?? '—',
            ];
        }

        $violations = ViolationLog::latest()->limit(3)->get(['violation_type', 'created_at']);
        foreach ($violations as $violation) {
            $activity[] = [
                'title' => 'Violation Detected',
                'description' => ucfirst(str_replace('_', ' ', (string) $violation->violation_type)),
                'time' => $violation->created_at->diffForHumans(),
            ];
        }

        return array_slice($activity, 0, 6);
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
