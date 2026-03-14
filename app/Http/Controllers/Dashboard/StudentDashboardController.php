<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Subject;
use App\Models\StudentAnswer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StudentDashboardController extends Controller
{
    public function __construct()
    {
//        $this->middleware('auth');
//        $this->middleware(function ($request, $next) {
            if (!Auth::user()->isStudent()) {
                abort(403, 'Unauthorized access. Student privileges required.');
            }

    }

    public function index()
    {
        $studentId = Auth::id();

        $resumeExams = $this->getResumeExams($studentId);
        $availableExams = $this->getAvailableExams($studentId);

        return view('dashboard.student.index', compact(
            'resumeExams',
            'availableExams'
        ));
    }

    /**
     * Show completed exam results for the logged in student.
     */
    public function results()
    {
        $studentId = Auth::id();

        $results = ExamSession::with(['exam'])
            ->where('student_id', $studentId)
            ->where('status', 'completed')
            ->orderByDesc('submitted_at')
            ->get()
            ->map(function ($session) {
                $marksSecured = (float) ($session->answers()->sum('points_earned') ?? 0);
                $totalMarks = (float) ($session->answers()->sum('max_points') ?? 0);

                return [
                    'session_id' => $session->id,
                    'exam_name' => $session->exam->title ?? 'N/A',
                    'marks_secured' => $marksSecured,
                    'total_marks' => $totalMarks,
                    'submitted_at' => optional($session->submitted_at)->format('M d, Y h:i A'),
                ];
            });

        return view('dashboard.student.results.index', compact('results'));
    }

    /**
     * Show detailed report for one completed session owned by the logged in student.
     */
    public function showResult(ExamSession $session)
    {
        if ($session->student_id !== Auth::id()) {
            abort(403, 'Unauthorized access.');
        }

        if ($session->status !== 'completed') {
            return redirect()->route('student.results.index')
                ->with('error', 'Result is available only after exam completion.');
        }

        $session->load(['exam.subject', 'answers.question']);

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
            });

        $summary = [
            'exam_name' => $session->exam->title ?? 'N/A',
            'subject' => $session->exam->subject->name ?? 'N/A',
            'submitted_at' => optional($session->submitted_at)->format('M d, Y h:i A'),
            'marks_secured' => (float) ($session->answers->sum('points_earned') ?? 0),
            'total_marks' => (float) ($session->answers->sum('max_points') ?? 0),
        ];

        return view('dashboard.student.results.show', [
            'summary' => $summary,
            'rows' => $rows,
        ]);
    }

    /**
     * Render answer values in a readable form across question types.
     */
    private function formatAnswerForDisplay(?\App\Models\Question $question, mixed $rawAnswer): string
    {
        $values = $this->normalizeAnswerValues($rawAnswer);

        if (empty($values)) {
            return 'Not answered';
        }

        $displayValues = [];

        foreach ($values as $value) {
            $displayValues[] = $this->formatSingleAnswerValue($question, $value);
        }

        return implode(', ', $displayValues);
    }

    private function normalizeAnswerValues(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;

                if (is_string($value)) {
                    $secondDecode = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $secondDecode;
                    }
                }
            }
        }

        if (is_array($value)) {
            return array_values(array_filter($value, static function ($item) {
                return $item !== null && $item !== '';
            }));
        }

        return [$value];
    }

    private function formatSingleAnswerValue(?\App\Models\Question $question, mixed $value): string
    {
        $stringValue = is_string($value) ? $value : (string) $value;

        if (!$question) {
            return $stringValue;
        }

        if (in_array($question->question_type, ['mcq_single', 'mcq_multiple'], true)) {
            $optionText = is_array($question->options) ? ($question->options[$stringValue] ?? null) : null;
            return $optionText ? ($stringValue . '. ' . $optionText) : $stringValue;
        }

        if ($question->question_type === 'true_false') {
            $normalized = strtolower(trim($stringValue));
            if (in_array($normalized, ['1', 'true', 'yes'], true)) {
                return 'True';
            }
            if (in_array($normalized, ['0', 'false', 'no'], true)) {
                return 'False';
            }

            return ucfirst($stringValue);
        }

        return $stringValue;
    }

    /**
     * Get dashboard statistics for student
     */
    private function getStats($studentId): array
    {
        $completedExams = ExamSession::where('student_id', $studentId)
            ->where('status', 'completed')
            ->count();

        $inProgressExams = ExamSession::where('student_id', $studentId)
            ->where('status', 'in_progress')
            ->count();

        // Calculate average score from completed exams using ExamSession's score field
        $averageScore = ExamSession::where('student_id', $studentId)
            ->where('status', 'completed')
            ->whereNotNull('score')
            ->avg('score') ?? 0;

        $totalSubjects = Subject::count();

        return [
            [
                'title' => 'Completed Exams',
                'value' => $completedExams,
                'icon' => 'bi-check-circle',
                'color' => 'success',
                'trend' => $completedExams > 0 ? 'up' : null
            ],
            [
                'title' => 'In Progress',
                'value' => $inProgressExams,
                'icon' => 'bi-hourglass-split',
                'color' => 'warning',
                'trend' => $inProgressExams > 0 ? 'up' : null
            ],
            [
                'title' => 'Average Score',
                'value' => round($averageScore) . '%',
                'icon' => 'bi-star',
                'color' => 'info',
            ],
            [
                'title' => 'Available Subjects',
                'value' => $totalSubjects,
                'icon' => 'bi-book',
                'color' => 'primary',
            ],
        ];
    }

    /**
     * Quick action buttons
     */
    private function getQuickActions(): array
    {
        return [
            [
                'label' => 'Available Exams',
                'route' => '#available-exams',
                'icon' => 'bi-calendar-check',
                'color' => 'success',
                'width' => 3
            ],
            [
                'label' => 'My Results',
                'route' => '#results',
                'icon' => 'bi-bar-chart',
                'color' => 'info',
                'width' => 3
            ],
            [
                'label' => 'Exam History',
                'route' => '#history',
                'icon' => 'bi-clock-history',
                'color' => 'primary',
                'width' => 3
            ],
            [
                'label' => 'Subjects',
                'route' => '#subjects',
                'icon' => 'bi-book',
                'color' => 'warning',
                'width' => 3
            ],
        ];
    }

    /**
     * Get exams that need to be resumed (in_progress)
     */
    /**
     * Get exams that need to be resumed (in_progress)
     */
    private function getResumeExams($studentId): array
    {
        $inProgressSessions = ExamSession::with(['exam.subject', 'exam.teacher'])
            ->where('student_id', $studentId)
            ->where('status', 'in_progress')
            ->get();

        return $inProgressSessions->map(function($session) {
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
                'progress' => $answered . '/' . $total,
                'percentage' => $percentage,
                'time_spent' => $session->time_spent,
                'available_until' => $exam->available_to ?
                    $exam->available_to->format('M d, Y h:i A') : 'No deadline',
            ];
        })->toArray();
    }

    /**
     * Get exams in progress (for stats)
     */
    private function getInProgressExamIds($studentId): array
    {
        return ExamSession::where('student_id', $studentId)
            ->where('status', 'in_progress')
            ->pluck('exam_id')
            ->toArray();
    }

    /**
     * Get completed exams that have reached max attempts
     */
    private function getMaxAttemptsReachedExamIds($studentId): array
    {
        // Get all completed sessions
        $completedSessions = ExamSession::where('student_id', $studentId)
            ->where('status', 'completed')
            ->with('exam')
            ->get();

        $excludeIds = [];

        // Group by exam_id and count attempts
        $attemptsByExam = $completedSessions->groupBy('exam_id');

        foreach ($attemptsByExam as $examId => $sessions) {
            $exam = $sessions->first()->exam;
            $attemptCount = $sessions->count();

            // If attempts reached or exceeded max_attempts, exclude this exam
            if ($attemptCount >= ($exam->max_attempts ?? 1)) {
                $excludeIds[] = $examId;
            }
        }

        return $excludeIds;
    }

    /**
     * Get truly available exams (not started or can be retaken)
     */
    private function getAvailableExams($studentId): array
    {
        // Get IDs of exams currently in progress
        $inProgressIds = $this->getInProgressExamIds($studentId);

        // Get IDs of exams that have reached max attempts
        $maxAttemptsIds = $this->getMaxAttemptsReachedExamIds($studentId);

        // Combine both arrays to exclude
        $excludeIds = array_merge($inProgressIds, $maxAttemptsIds);

        // Base query for available exams
        $query = Exam::with(['subject', 'teacher'])
            ->where('status', 'published')
            ->where(function($q) {
                $q->whereNull('available_from')
                    ->orWhere('available_from', '<=', now());
            })
            ->where(function($q) {
                $q->whereNull('available_to')
                    ->orWhere('available_to', '>=', now());
            });

        // Exclude exams that are in progress or max attempts reached
        if (!empty($excludeIds)) {
            $query->whereNotIn('id', $excludeIds);
        }

        return $query->orderBy('updated_at', 'desc')
            ->get()
            ->map(function($exam) {
                return [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'subject' => $exam->subject->name ?? 'N/A',
                    'teacher' => $exam->teacher->name ?? 'N/A',
                    'duration' => $exam->time_limit,
                    'total_marks' => $exam->total_marks,
                    'questions_count' => $exam->questions()->count(),
                    'available_until' => $exam->available_to ?
                        $exam->available_to->format('M d, Y h:i A') : 'No deadline',
                ];
            })
            ->toArray();
    }



    /**
     * Get upcoming exams (scheduled for future)
     */
    private function getUpcomingExams(): array
    {
        return Exam::with(['subject'])
            ->where('status', 'published')
            ->where('available_from', '>', now())
            ->orderBy('available_from', 'asc')
            ->take(3)
            ->get()
            ->map(function($exam) {
                return [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'subject' => $exam->subject->name ?? 'N/A',
                    'available_from' => $exam->available_from->format('M d, Y h:i A'),
                    'duration' => $exam->time_limit,
                ];
            })
            ->toArray();
    }

    /**
     * Get past exam results for the student
     * Uses ExamSession and StudentAnswer directly without Grade model
     */
    private function getPastResults($studentId): array
    {
        $sessions = ExamSession::with(['exam.subject', 'answers'])
            ->where('student_id', $studentId)
            ->where('status', 'completed')
            ->orderBy('submitted_at', 'desc')
            ->take(5)
            ->get();

        return $sessions->map(function($session) {
            // Calculate statistics from answers
            $totalQuestions = $session->answers->count();
            $correctAnswers = $session->answers->where('is_correct', true)->count();
            $pointsEarned = $session->answers->sum('points_earned');
            $pointsPossible = $session->answers->sum('max_points');

            // Calculate percentage
            $percentage = $pointsPossible > 0
                ? round(($pointsEarned / $pointsPossible) * 100, 2)
                : 0;

            // Determine pass/fail using exam's passing marks
            $passingMarks = $session->exam->passing_marks ?? 40;
            $passed = $percentage >= $passingMarks;

            return [
                'id' => $session->id,
                'exam_title' => $session->exam->title ?? 'N/A',
                'subject' => $session->exam->subject->name ?? 'N/A',
                'submitted_at' => $session->submitted_at->format('M d, Y h:i A'),
                'score' => $percentage,
                'marks_obtained' => $pointsEarned,
                'total_marks' => $pointsPossible,
                'correct_count' => $correctAnswers,
                'total_questions' => $totalQuestions,
                'status' => $passed ? 'passed' : 'failed',
            ];
        })->toArray();
    }

    /**
     * Get recent activity for the student
     */
    private function getRecentActivity($studentId): array
    {
        $activities = [];

        // Recent exam completions
        $recentCompletions = ExamSession::with('exam')
            ->where('student_id', $studentId)
            ->where('status', 'completed')
            ->latest('submitted_at')
            ->take(3)
            ->get();

        foreach ($recentCompletions as $completion) {
            // Calculate score for display
            $correctCount = StudentAnswer::where('exam_session_id', $completion->id)
                ->where('is_correct', true)
                ->count();
            $totalCount = $completion->answers()->count();

            $activities[] = [
                'title' => 'Exam Completed',
                'description' => "You completed: {$completion->exam->title} " .
                    "(Score: {$correctCount}/{$totalCount})",
                'time' => $completion->submitted_at->diffForHumans(),
                'icon' => 'bi-check-circle',
                'color' => 'success'
            ];
        }

        // Recent exam starts
        $recentStarts = ExamSession::with('exam')
            ->where('student_id', $studentId)
            ->where('status', 'in_progress')
            ->latest('started_at')
            ->take(2)
            ->get();

        foreach ($recentStarts as $start) {
            $activities[] = [
                'title' => 'Exam Started',
                'description' => "You started: {$start->exam->title}",
                'time' => $start->started_at->diffForHumans(),
                'icon' => 'bi-play-circle',
                'color' => 'info'
            ];
        }

        // Sort by time (most recent first)
        usort($activities, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });

        return $activities;
    }

    /**
     * Get detailed results for a specific exam session
     * Optional helper method for detailed view
     */
    public function getDetailedResults($sessionId)
    {
        $session = ExamSession::with(['exam.subject', 'answers.question'])
            ->where('student_id', Auth::id())
            ->where('id', $sessionId)
            ->where('status', 'completed')
            ->firstOrFail();

        $results = [
            'exam' => [
                'title' => $session->exam->title,
                'subject' => $session->exam->subject->name,
                'date' => $session->submitted_at->format('M d, Y h:i A'),
                'time_taken' => $session->time_spent,
            ],
            'summary' => [
                'total_questions' => $session->answers->count(),
                'answered' => $session->answers->where('is_answered', true)->count(),
                'correct' => $session->answers->where('is_correct', true)->count(),
                'incorrect' => $session->answers->where('is_answered', true)->where('is_correct', false)->count(),
                'unanswered' => $session->answers->where('is_answered', false)->count(),
                'points_earned' => $session->answers->sum('points_earned'),
                'points_possible' => $session->answers->sum('max_points'),
                'percentage' => $session->answers->sum('max_points') > 0
                    ? round(($session->answers->sum('points_earned') / $session->answers->sum('max_points')) * 100, 2)
                    : 0,
            ],
            'answers' => $session->answers->map(function($answer) {
                return [
                    'question' => $answer->question->question_text,
                    'type' => $answer->question->question_type,
                    'student_answer' => $answer->answer,
                    'correct_answer' => $answer->question->correct_answers,
                    'is_correct' => $answer->is_correct,
                    'points_earned' => $answer->points_earned,
                    'max_points' => $answer->max_points,
                ];
            }),
        ];

        return view('student.results.detailed', compact('results'));
    }
}
