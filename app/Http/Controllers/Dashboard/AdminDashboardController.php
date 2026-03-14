<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\User;
use App\Models\Subject;
use App\Models\Question;
use App\Models\Exam;
use App\Models\ExamSession;
use Illuminate\Http\Request;

class AdminDashboardController extends BaseDashboardController
{
    protected function checkAccess(): bool
    {
        return $this->user->isAdmin();
    }

    protected function getStats(): array
    {
        return [
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
    }

    protected function getQuickActions(): array
    {
        return [
            [
                'label' => 'Manage Users',
                'route' => route('users.index'),
                'icon' => 'bi-people',
                'color' => 'primary',
                'width' => 3
            ],
            [
                'label' => 'Manage Subjects',
                'route' => route('subjects.index'),
                'icon' => 'bi-book',
                'color' => 'info',
                'width' => 3
            ],
            [
                'label' => 'Manage Questions',
                'route' => route('questions.index'),
                'icon' => 'bi-question-circle',
                'color' => 'warning',
                'width' => 3
            ],
            [
                'label' => 'Manage Exams',
                'route' => route('exams.index'),
                'icon' => 'bi-file-text',
                'color' => 'danger',
                'width' => 3
            ],
            [
                'label' => 'Add New User',
                'route' => route('users.create'),
                'icon' => 'bi-person-plus',
                'color' => 'success',
                'width' => 2
            ],
            [
                'label' => 'System Reports',
                'route' => '#',
                'icon' => 'bi-bar-chart',
                'color' => 'secondary',
                'width' => 2
            ],
        ];
    }

    protected function getRecentActivity(): array
    {
        // Fetch recent activities from logs or events
        return [
            [
                'title' => 'New User Registered',
                'description' => 'John Doe created a teacher account',
                'time' => '5 minutes ago',
            ],
            [
                'title' => 'Exam Published',
                'description' => 'Mathematics Final Exam was published',
                'time' => '1 hour ago',
            ],
            // ... more activities
        ];
    }

    public function index()
    {
        $stats = $this->getStats();
        $quickActions = $this->getQuickActions();
        $recentActivity = $this->getRecentActivity();

        $activeSessions = ExamSession::with(['exam:id,title', 'student:id,name,email'])
            ->whereIn('status', ['in_progress', 'paused'])
            ->latest('started_at')
            ->limit(25)
            ->get();

        $sessionCounts = [
            'in_progress' => $activeSessions->where('status', 'in_progress')->count(),
            'paused'      => $activeSessions->where('status', 'paused')->count(),
        ];

        return view('dashboard.admin.index', compact(
            'stats',
            'quickActions',
            'recentActivity',
            'activeSessions',
            'sessionCounts'
        ));
    }
    public function activeSessions(Request $request)
    {
        $statusFilter = $request->query('status');
        $allowedStatuses = ['in_progress', 'paused'];

        $query = ExamSession::with(['exam:id,title', 'student:id,name,email', 'teacher:id,name'])
            ->whereIn('status', $allowedStatuses);

        if (in_array($statusFilter, $allowedStatuses, true)) {
            $query->where('status', $statusFilter);
        }

        $activeSessions = $query->latest('started_at')->paginate(12)->withQueryString();

        $statusCounts = [
            'in_progress' => ExamSession::where('status', 'in_progress')->count(),
            'paused'      => ExamSession::where('status', 'paused')->count(),
        ];

        return view('dashboard.admin.active-sessions', [
            'activeSessions' => $activeSessions,
            'statusFilter'   => $statusFilter,
            'statusCounts'   => $statusCounts,
        ]);
    }
}
