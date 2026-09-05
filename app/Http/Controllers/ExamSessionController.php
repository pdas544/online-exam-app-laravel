<?php

namespace App\Http\Controllers;

use App\Http\Requests\LogViolationRequest;
use App\Http\Requests\SaveAnswerRequest;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\StudentAnswer;
use App\Models\Question;
use App\Events\ExamEnded;
use App\Events\AnswerSaved;
use App\Events\StudentJoined;
use App\Events\ViolationDetected;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExamSessionController extends Controller
{
    public function __construct()
    {
//        $this->middleware('auth');
    }

    /**
     * Start an exam for a student
     */
    public function start(Exam $exam)
    {
        // Check if exam is available
        if (!$exam->isAvailable()) {
            return back()->with('error', 'This exam is not available at this time.');
        }

        // Resume an existing active session if one exists
        $activeSession = ExamSession::where('exam_id', $exam->id)
            ->where('student_id', Auth::id())
            ->whereIn('status', ['scheduled', 'in_progress', 'paused'])
            ->first();

        if ($activeSession) {
            return redirect()->route('exam.session.resume', $activeSession);
        }

        // Enforce max attempts against completed sessions
        $completedAttempts = ExamSession::where('exam_id', $exam->id)
            ->where('student_id', Auth::id())
            ->where('status', 'completed')
            ->count();

        if ($completedAttempts >= ($exam->max_attempts ?? 1)) {
            return back()->with('error', 'You have already completed this exam.');
        }

        // Create new session
        DB::beginTransaction();
        try {
            $questions = $exam->questions()->orderBy('order_index')->get();

            $session = ExamSession::create([
                'exam_id' => $exam->id,
                'student_id' => Auth::id(),
                'teacher_id' => $exam->teacher_id,
                'status' => 'scheduled',
                'started_at' => null,
                'total_questions' => $questions->count(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            // Create answer records for each question
            foreach ($questions as $question) {
                StudentAnswer::create([
                    'exam_session_id' => $session->id,
                    'question_id' => $question->id,
                    'exam_id' => $exam->id,
                    'max_points' => $question->pivot->points_override ?? $question->points,
                ]);
            }

            DB::commit();

            $session->loadMissing('student');
            broadcast(new StudentJoined($session));

            return redirect()->route('exam.session.take', $session);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to start exam: ' . $e->getMessage());
        }
    }

    /**
     * Take the exam (main interface)
     */
    public function take(ExamSession $session)
    {
        $this->authorize('view', $session);

        $session->load(['exam', 'exam.questions', 'answers' => function($q) {
            $q->with('question');
        }]);

        return view('exams.take', compact('session'));
    }

    /**
     * Resume an in-progress exam
     */
    public function resume(ExamSession $session)
    {
        $this->authorize('view', $session);

        if (!in_array($session->status, ['scheduled', 'in_progress', 'paused'], true)) {
            return redirect()->route($this->dashboardRoute())
                ->with('error', 'This exam session cannot be resumed.');
        }

        return redirect()->route('exam.session.take', $session);
    }

    /**
     * Save answer (AJAX endpoint)
     */
    public function saveAnswer(SaveAnswerRequest $request, ExamSession $session)
    {
        $this->authorize('view', $session);

        $answer = StudentAnswer::where('exam_session_id', $session->id)
            ->where('question_id', $request->question_id)
            ->firstOrFail();

        $answer->update([
            'answer' => $request->answer,
            'is_answered' => $request->answer !== null && $request->answer !== '',
            'is_marked_for_review' => $request->is_marked_for_review ?? $answer->is_marked_for_review,
            'answered_at' => now(),
        ]);

        // Update session progress
        $session->updateProgress();

        return response()->json([
            'success' => true,
            'progress' => [
                'answered' => $session->answers()->where('is_answered', true)->count(),
                'total' => $session->total_questions,
            ],
        ]);
    }

    /**
     * Submit exam
     */
    public function submit(Request $request, ExamSession $session)
    {
        try {
            $this->authorize('view', $session);

            if ($session->status !== 'in_progress') {
                return response()->json(['error' => 'Exam already submitted'], 400);
            }

            DB::beginTransaction();

            try {
                // Calculate time spent in seconds (ensure positive integer)
                $timeSpent = $session->started_at 
                    ? abs((int) $session->started_at->diffInSeconds(now(), false))
                    : 0;

                // Update session first
                $session->update([
                    'status' => 'completed',
                    'submitted_at' => now(),
                    'time_spent' => $timeSpent,
                ]);

                // Auto-grade all answers
                $session->load('answers.question');
                foreach ($session->answers as $answer) {
                    if (!$answer->is_answered) {
                        $answer->update([
                            'is_correct' => false,
                            'points_earned' => 0,
                        ]);
                        continue;
                    }

                    $answer->autoGrade();
                }

                // Calculate score
                $totalEarned = $session->answers()->sum('points_earned') ?: 0;
                $totalPossible = $session->answers()->sum('max_points') ?: 1; // Avoid division by zero
                $score = ($totalEarned / $totalPossible) * 100;

                $session->update([
                    'score' => round($score, 2),
                    'passed' => $score >= ($session->exam->passing_marks ?? 40),
                ]);

                DB::commit();

                $session->loadMissing('student');
                broadcast(new ExamEnded($session, 'completed'));

                // Return success response
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'redirect' => route('student.dashboard')
                    ]);
                }

                return redirect()->route('student.dashboard')
                    ->with('success', 'Exam submitted successfully!');

            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('Exam submission failed: ' . $e->getMessage(), [
                    'session_id' => $session->id,
                    'trace' => $e->getTraceAsString()
                ]);

                if ($request->wantsJson()) {
                    return response()->json(['error' => 'Failed to submit exam: ' . $e->getMessage()], 500);
                }

                return back()->with('error', 'Failed to submit exam: ' . $e->getMessage());
            }

        } catch (\Exception $e) {
            \Log::error('Exam submission authorization failed: ' . $e->getMessage());
            return response()->json(['error' => 'Unauthorized'], 403);
        }
    }

    /**
     * Show exam results
     */
    public function result(ExamSession $session)
    {
        $this->authorize('view', $session);

        if ($session->status !== 'completed') {
            return redirect()->route('exam.session.take', $session);
        }

        return redirect()->route('student.results.show', $session);
    }

    /**
     * Log violation (AJAX endpoint)
     */
    public function logViolation(LogViolationRequest $request, ExamSession $session)
    {
        $this->authorize('view', $session);

        $violation = $session->logViolation(
            $request->type,
            $request->description,
            $request->metadata ?? []
        );

        // Pause session on focus-loss type violations
        $focusLossTypes = ['tab_switch', 'window_blur', 'fullscreen_exit', 'tab_key'];
        if (in_array($request->type, $focusLossTypes, true) && $session->status === 'in_progress') {
            $session->update([
                'status' => 'paused',
                'last_activity_at' => now(),
            ]);
        }

        // Notify teacher via broadcast
        broadcast(new ViolationDetected($violation))->toOthers();

        // If auto-terminated, return special response
        if ($session->status === 'terminated') {
            return response()->json([
                'terminated' => true,
                'reason' => 'Multiple violations detected',
                'redirect' => route($this->dashboardRoute()),
            ]);
        }

        return response()->json([
            'success' => true,
            'violation_count' => $session->violation_count,
            'warning' => $session->violation_count >= 3 ?
                'Warning: Further violations will terminate your exam.' : null,
        ]);
    }

    /**
     * Get session status (AJAX polling)
     */
    public function status(ExamSession $session)
    {
        $this->authorize('view', $session);

        $timeRemaining = $this->calculateTimeRemaining($session);

        return response()->json([
            'status' => $session->status,
            'time_remaining' => $timeRemaining,
            'progress' => [
                'answered' => $session->answers()->where('is_answered', true)->count(),
                'total' => $session->total_questions,
            ],
            'violation_count' => $session->violation_count,
        ]);
    }


    /**
     * Calculate time remaining
     */
    private function calculateTimeRemaining(ExamSession $session)
    {
        if (!$session->started_at) {
            return $session->exam->time_limit * 60;
        }

        $elapsed = now()->diffInSeconds($session->started_at);
        $total = $session->exam->time_limit * 60;
        $remaining = $total - $elapsed;

        return max(0, $remaining);
    }

    /**
     * Teacher: Force end exam session
     */
    public function forceEnd(ExamSession $session)
    {
        $this->authorize('forceEnd', $session);

        $session->update([
            'status' => 'terminated',
            'submitted_at' => now(),
        ]);

        broadcast(new ExamEnded($session, 'terminated_by_teacher'))->toOthers();
        broadcast(new \App\Events\ExamForceEnded($session))->toOthers();

        return back()->with('success', 'Exam session terminated.');
    }

    /**
     * Dashboard route for the current user (no single `dashboard` route exists).
     */
    private function dashboardRoute(): string
    {
        return Auth::user()->isAdmin() ? 'admin.dashboard' : 'student.dashboard';
    }

    /**
     * Calculate letter grade from percentage
     */
    private function calculateGrade($percentage)
    {
        if ($percentage >= 90) return 'A';
        if ($percentage >= 80) return 'B';
        if ($percentage >= 70) return 'C';
        if ($percentage >= 60) return 'D';
        return 'F';
    }
}
