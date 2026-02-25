<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Events\ExamResumed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LiveMonitoringController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (!Auth::user()->isTeacher() && !Auth::user()->isAdmin()) {
                abort(403);
            }
            return $next($request);
        });
    }

    /**
     * Show live monitoring dashboard
     */
    public function index()
    {
        $teacherId = Auth::id();

        $activeExams = Exam::where('teacher_id', $teacherId)
            ->whereHas('sessions', function($q) {
                $q->where('status', 'in_progress');
            })
            ->withCount(['sessions' => function($q) {
                $q->where('status', 'in_progress');
            }])
            ->get();

        return view('teacher.monitoring.index', compact('activeExams'));
    }

    /**
     * Monitor specific exam
     */
    public function monitor(Exam $exam)
    {
        if (!Auth::user()->isAdmin() && $exam->teacher_id !== Auth::id()) {
            abort(403);
        }

        $sessions = $exam->sessions()
            ->with('student')
            ->where('status', 'in_progress')
            ->get();

        return view('teacher.monitoring.exam', compact('exam', 'sessions'));
    }

    /**
     * Get live session data (AJAX)
     */
    public function getSessions(Exam $exam)
    {
        if (!Auth::user()->isAdmin() && $exam->teacher_id !== Auth::id()) {
            abort(403);
        }

        $sessions = $exam->sessions()
            ->with('student')
            ->whereIn('status', ['in_progress', 'paused'])
            ->get()
            ->map(function($session) {
                return [
                    'id' => $session->id,
                    'student_name' => $session->student->name,
                    'status' => $session->status,
                    'progress' => $session->answers()->where('is_answered', true)->count(),
                    'total' => $session->total_questions,
                    'time_spent' => $session->time_spent,
                    'violations' => $session->violation_count,
                    'last_activity' => $session->last_activity_at?->diffForHumans(),
                ];
            });

        return response()->json([
            'sessions' => $sessions,
            'total_active' => $sessions->count(),
        ]);
    }

    /**
     * Send warning to student
     */
    public function sendWarning(Request $request, ExamSession $session)
    {
        if (!Auth::user()->isAdmin() && $session->teacher_id !== Auth::id()) {
            abort(403);
        }

        broadcast(new \App\Events\TeacherWarning(
            $session,
            $request->message ?? 'Please focus on your exam.'
        ))->toOthers();

        return response()->json(['success' => true]);
    }

    /**
     * Allow student to resume a paused exam
     */
    public function resumeSession(ExamSession $session)
    {
        if (!Auth::user()->isAdmin() && $session->teacher_id !== Auth::id()) {
            abort(403);
        }

        if ($session->status === 'paused') {
            $session->update([
                'status' => 'in_progress',
                'last_activity_at' => now(),
            ]);
        }

        broadcast(new ExamResumed($session->id, $session->student_id))->toOthers();

        return response()->json(['success' => true]);
    }
}
