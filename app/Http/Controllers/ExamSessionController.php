<?php

namespace App\Http\Controllers;

use App\Events\ExamEnded;
use App\Events\StudentJoined;
use App\Events\ViolationDetected;
use App\Http\Requests\LogViolationRequest;
use App\Http\Requests\SaveAnswerRequest;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\StudentAnswer;
use App\Services\ExamSessionService;
use App\Services\ViolationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamSessionController extends Controller
{
    public function __construct(
        private ExamSessionService $sessions,
        private ViolationService $violations,
    ) {}

    /**
     * Start an exam for a student
     */
    public function start(Exam $exam)
    {
        try {
            $session = $this->sessions->start($exam, Auth::id());

            if (! $session->wasRecentlyCreated) {
                return redirect()->route('exam.session.resume', $session);
            }

            $session->loadMissing('student');
            broadcast(new StudentJoined($session));

            return redirect()->route('exam.session.take', $session);
        } catch (\DomainException $e) {
            return back()->with('error', 'Could not start exam. Please try again.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to start exam. Please try again.');
        }
    }

    /**
     * Take the exam (main interface)
     */
    public function take(ExamSession $session)
    {
        $this->authorize('view', $session);

        $session->load(['exam', 'exam.questions', 'answers' => function ($q) {
            $q->with('question');
        }]);

        return view('exams.take', compact('session'));
    }

    /**
     * Begin the attempt (lobby proceed): scheduled → in_progress.
     * Returns server-authoritative time remaining for timer seeding.
     */
    public function begin(ExamSession $session)
    {
        $this->authorize('view', $session);

        try {
            $session = $this->sessions->begin($session->fresh());
        } catch (\DomainException $e) {
            return response()->json(['error' => 'This exam session can no longer be taken.'], 422);
        }

        return response()->json([
            'success' => true,
            'status' => $session->status,
            'time_remaining' => $session->timeRemaining(),
        ]);
    }

    /**
     * Resume an in-progress exam
     */
    public function resume(ExamSession $session)
    {
        $this->authorize('view', $session);

        if (! in_array($session->status, ['scheduled', 'in_progress', 'paused'], true)) {
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

            try {
                $this->sessions->submit($session->fresh());

                $session->loadMissing('student');
                broadcast(new ExamEnded($session, 'completed'));

                // Return success response
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'redirect' => route('student.dashboard'),
                    ]);
                }

                return redirect()->route('student.dashboard')
                    ->with('success', 'Exam submitted successfully!');

            } catch (\DomainException $e) {
                if ($request->wantsJson()) {
                    return response()->json(['error' => 'Could not submit exam. Please try again.'], 400);
                }

                return back()->with('error', 'Could not submit exam. Please try again.');
            } catch (\Exception $e) {
                \Log::error('Exam submission failed: '.$e->getMessage(), [
                    'session_id' => $session->id,
                    'trace' => $e->getTraceAsString(),
                ]);

                if ($request->wantsJson()) {
                    return response()->json(['error' => 'Failed to submit exam. Please try again.'], 500);
                }

                return back()->with('error', 'Failed to submit exam. Please try again.');
            }

        } catch (\Exception $e) {
            \Log::error('Exam submission authorization failed: '.$e->getMessage());

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

        $violation = $this->violations->record(
            $session,
            $request->type,
            $request->description,
            $request->metadata ?? []
        );

        $this->violations->pauseOnFocusLoss($session, $request->type);

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

        $timeRemaining = $session->timeRemaining();

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
     * Teacher: Force end exam session
     */
    public function forceEnd(ExamSession $session)
    {
        $this->authorize('forceEnd', $session);

        $this->sessions->forceEnd($session);

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
}
