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

        $stats = $this->getStats($studentId);
        $quickActions = $this->getQuickActions();
        $resumeExams = $this->getResumeExams($studentId);      // NEW
        $availableExams = $this->getAvailableExams($studentId);
        $upcomingExams = $this->getUpcomingExams();
        $pastResults = $this->getPastResults($studentId);
        $recentActivity = $this->getRecentActivity($studentId);

        return view('dashboard.student.index', compact(
            'stats',
            'quickActions',
            'resumeExams',                                     // NEW
            'availableExams',
            'upcomingExams',
            'pastResults',
            'recentActivity'
        ));
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
