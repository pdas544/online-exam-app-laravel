<?php

namespace App\Http\Controllers\Teacher;

use App\Events\ExamResumed;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendWarningRequest;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Services\ExamSessionService;
use Illuminate\Support\Facades\Auth;

class LiveMonitoringController extends Controller
{
    public function __construct(private ExamSessionService $sessions) {}

    /**
     * Show live monitoring dashboard
     */
    public function index()
    {
        $teacherId = Auth::id();

        $activeExams = Exam::where('teacher_id', $teacherId)
            ->whereHas('sessions', function($q) {
                $q->whereIn('status', ['scheduled', 'in_progress', 'paused']);
            })
            ->withCount(['sessions' => function($q) {
                $q->whereIn('status', ['scheduled', 'in_progress', 'paused']);
            }])
            ->get();

        return view('dashboard.teacher.monitoring.index', compact('activeExams'));
    }

    /**
     * Monitor specific exam
     */
    public function monitor(Exam $exam)
    {
        $this->authorize('update', $exam);

        $sessions = $exam->sessions()
            ->with('student')
            ->withCount([
                'answers as answered_answers_count' => function ($query) {
                    $query->where('is_answered', true);
                },
            ])
            ->whereIn('status', ['scheduled', 'in_progress', 'paused', 'completed', 'terminated'])
            ->latest('updated_at')
            ->get();

        return view('dashboard.teacher.monitoring.exam', compact('exam', 'sessions'));
    }

    /**
     * Get live session data (AJAX)
     */
    public function getSessions(Exam $exam)
    {
        $this->authorize('update', $exam);

        $sessions = $exam->sessions()
            ->with('student')
            ->withCount([
                'answers as answered_answers_count' => function ($query) {
                    $query->where('is_answered', true);
                },
            ])
            ->whereIn('status', ['scheduled', 'in_progress', 'paused', 'completed', 'terminated'])
            ->latest('updated_at')
            ->get()
            ->map(function ($session) {
                $liveTimeSpent = $session->time_spent;

                if ($session->status === 'in_progress' && $session->started_at) {
                    $liveTimeSpent = max(0, now()->diffInSeconds($session->started_at));
                }

                return [
                    'id' => $session->id,
                    'student_name' => $session->student->name,
                    'student_email' => $session->student->email,
                    'status' => $session->status,
                    'progress' => $session->answered_answers_count,
                    'total' => $session->total_questions,
                    'time_spent' => $liveTimeSpent,
                    'violations' => $session->violation_count,
                    'last_activity' => $session->last_activity_at?->diffForHumans() ?? 'Just now',
                ];
            });

        $totalActive = $sessions->whereIn('status', ['scheduled', 'in_progress', 'paused'])->count();

        return response()->json([
            'sessions' => $sessions->values(),
            'total_active' => $totalActive,
        ]);
    }

    /**
     * Start exam for all scheduled sessions (lobby approval)
     */
    public function startExam(Exam $exam)
    {
        $this->authorize('update', $exam);

        $sessions = $exam->sessions()
            ->where('status', 'scheduled')
            ->get();

        foreach ($sessions as $session) {
            $session->update([
                'status' => 'in_progress',
                'started_at' => now(),
                'last_activity_at' => now(),
            ]);

            broadcast(new \App\Events\ExamStartAllowed($session))->toOthers();
        }

        return response()->json([
            'success' => true,
            'started' => $sessions->count(),
        ]);
    }

    /**
     * Send warning to student
     */
    public function sendWarning(SendWarningRequest $request, ExamSession $session)
    {
        $this->authorize('forceEnd', $session);

        broadcast(new \App\Events\TeacherWarning(
            $session,
            $request->validated()['message'] ?? 'Please focus on your exam.'
        ))->toOthers();

        return response()->json(['success' => true]);
    }

    /**
     * Allow student to resume a paused exam
     */
    public function resumeSession(ExamSession $session)
    {
        $this->authorize('forceEnd', $session);

        try {
            $session = $this->sessions->resume($session);
        } catch (\DomainException $e) {
            return response()->json(['error' => 'Only a paused exam session can be resumed.'], 422);
        }

        broadcast(new ExamResumed($session->id, $session->student_id))->toOthers();

        return response()->json(['success' => true]);
    }

    /**
     * View session details
     */
    public function showSession(ExamSession $session)
    {
        $this->authorize('forceEnd', $session);

        $session->load(['student', 'exam']);

        return view('dashboard.teacher.monitoring.session', compact('session'));
    }
}
