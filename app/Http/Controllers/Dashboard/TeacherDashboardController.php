<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Question;
use App\Models\Exam;
use App\Models\ExamSession;
use Illuminate\Support\Facades\Auth;

class TeacherDashboardController extends Controller
{
    public function __construct()
    {

            if (!Auth::user()->isTeacher()) {
                abort(403, 'Unauthorized access. Teacher privileges required.');
            }

    }

    public function index()
    {
        $teacherId = Auth::id();

        $stats = $this->getStats($teacherId);
        $quickActions = $this->getQuickActions();
        $recentActivity = $this->getRecentActivity($teacherId);
        $upcomingExams = $this->getUpcomingExams($teacherId);
        $subjectPerformance = $this->getSubjectPerformance($teacherId);

        return view('dashboard.teacher.index', compact(
            'stats',
            'quickActions',
            'recentActivity',
            'upcomingExams',
            'subjectPerformance'
        ));
    }

    private function getStats($teacherId): array
    {
        $totalSubjects = Subject::where('created_by', $teacherId)->count();
        $totalQuestions = Question::where('created_by', $teacherId)->count();
        $totalExams = Exam::where('teacher_id', $teacherId)->count();
        $publishedExams = Exam::where('teacher_id', $teacherId)
            ->where('status', 'published')
            ->count();

        //exam session
        $activeSessions = ExamSession::whereIn('exam_id',
            Exam::where('teacher_id', $teacherId)->pluck('id')
        )->where('status', 'in_progress')->count();
        $activeSessions = null;

        return [
            [
                'title' => 'My Subjects',
                'value' => $totalSubjects,
                'icon' => 'bi-book',
                'color' => 'primary',
                'link' => route('subjects.index')
            ],
            [
                'title' => 'My Questions',
                'value' => $totalQuestions,
                'icon' => 'bi-question-circle',
                'color' => 'success',
                'link' => route('questions.index')
            ],
            [
                'title' => 'Total Exams',
                'value' => $totalExams,
                'icon' => 'bi-file-text',
                'color' => 'info',
                'link' => route('exams.index')
            ],
            [
                'title' => 'Published Exams',
                'value' => $publishedExams,
                'icon' => 'bi-check-circle',
                'color' => 'warning',
                'link' => route('exams.index', ['status' => 'published'])
            ],
            [
                'title' => 'Active Sessions',
                'value' => $activeSessions,
                'icon' => 'bi-people',
                'color' => 'danger',
                'link' => '#'
            ],
        ];
    }

    private function getQuickActions(): array
    {
        return [
            [
                'label' => 'Create Subject',
                'route' => route('subjects.create'),
                'icon' => 'bi-book-plus',
                'color' => 'primary',
                'width' => 3
            ],
            [
                'label' => 'Add Question',
                'route' => route('questions.create'),
                'icon' => 'bi-patch-plus',
                'color' => 'success',
                'width' => 3
            ],
            [
                'label' => 'Create Exam',
                'route' => route('exams.create'),
                'icon' => 'bi-file-plus',
                'color' => 'info',
                'width' => 3
            ],
            [
                'label' => 'Manage Exams',
                'route' => route('exams.index'),
                'icon' => 'bi-list-task',
                'color' => 'warning',
                'width' => 3
            ],
            [
                'label' => 'Question Bank',
                'route' => route('questions.index'),
                'icon' => 'bi-database',
                'color' => 'secondary',
                'width' => 2
            ],
            [
                'label' => 'Live Monitoring',
                'route' => '#',
                'icon' => 'bi-camera-video',
                'color' => 'danger',
                'width' => 2
            ],
        ];
    }

    private function getRecentActivity($teacherId): array
    {
        $activities = [];

        // Recent exams created
        $recentExams = Exam::where('teacher_id', $teacherId)
            ->latest()
            ->take(3)
            ->get();

        foreach ($recentExams as $exam) {
            $activities[] = [
                'title' => 'Exam Created',
                'description' => "You created exam: {$exam->title}",
                'time' => $exam->created_at->diffForHumans(),
                'icon' => 'bi-file-text',
                'color' => 'info'
            ];
        }

        // Recent questions added
        $recentQuestions = Question::where('created_by', $teacherId)
            ->latest()
            ->take(3)
            ->get();

        foreach ($recentQuestions as $question) {
            $activities[] = [
                'title' => 'Question Added',
                'description' => "Added a new " . str_replace('_', ' ', $question->question_type) . " question",
                'time' => $question->created_at->diffForHumans(),
                'icon' => 'bi-question-circle',
                'color' => 'success'
            ];
        }

        // Sort by time (most recent first)
        usort($activities, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });

        return array_slice($activities, 0, 5);
    }

    private function getUpcomingExams($teacherId): array
    {
        return Exam::where('teacher_id', $teacherId)
            ->where('status', 'published')
            ->where(function($query) {
                $query->whereNull('available_to')
                    ->orWhere('available_to', '>=', now());
            })
            ->withCount('questions')
            ->orderBy('available_from', 'asc')
            ->take(5)
            ->get()
            ->map(function($exam) {
                return [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'subject' => $exam->subject->name ?? 'N/A',
                    'question_count' => $exam->questions_count,
                    'total_marks' => $exam->total_marks,
                    'time_limit' => $exam->time_limit,
                    'available_from' => $exam->available_from ?
                        $exam->available_from->format('M d, Y h:i A') : 'Anytime',
                    'status' => $exam->isAvailable() ? 'available' : 'upcoming'
                ];
            })
            ->toArray();
    }

    private function getSubjectPerformance($teacherId): array
    {
        $subjects = Subject::where('created_by', $teacherId)
            ->withCount('questions')
            ->withCount('exams')
            ->get();

        return $subjects->map(function($subject) {
            return [
                'name' => $subject->name,
                'questions' => $subject->questions_count,
                'exams' => $subject->exams_count,
                'color' => $this->randomColor(),
            ];
        })->toArray();
    }

    private function randomColor(): string
    {
        $colors = ['primary', 'success', 'info', 'warning', 'danger', 'secondary'];
        return $colors[array_rand($colors)];
    }
}
