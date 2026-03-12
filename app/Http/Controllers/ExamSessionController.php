<?php

namespace App\Http\Controllers;

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

        // Check if student has already attempted
        $existingSession = ExamSession::where('exam_id', $exam->id)
            ->where('student_id', Auth::id())
            ->whereIn('status', ['scheduled', 'in_progress', 'paused', 'completed'])
            ->first();

        if ($existingSession) {
            if (in_array($existingSession->status, ['scheduled', 'in_progress', 'paused'], true)) {
                return redirect()->route('exam.session.resume', $existingSession);
            }
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
        $this->authorizeSession($session);

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
        $this->authorizeSession($session);

        if (!in_array($session->status, ['scheduled', 'in_progress', 'paused'], true)) {
            return redirect()->route('dashboard')
                ->with('error', 'This exam session cannot be resumed.');
        }

        return redirect()->route('exam.session.take', $session);
    }

    /**
     * Mark exam attempt as actually begun (after lobby proceed)
     */
    public function begin(ExamSession $session)
    {
        $this->authorizeSession($session);

        if (in_array($session->status, ['completed', 'terminated', 'expired'], true)) {
            return response()->json(['error' => 'Exam session is no longer active.'], 400);
        }

        if (!$session->started_at) {
            $session->update([
                'status' => 'in_progress',
                'started_at' => now(),
                'last_activity_at' => now(),
                'remaining_time' => $session->remaining_time ?? ($session->exam->time_limit * 60),
            ]);
        }

        return response()->json([
            'success' => true,
            'time_remaining' => $this->calculateTimeRemaining($session->fresh()),
        ]);
    }

    /**
     * Save answer (AJAX endpoint)
     */
    public function saveAnswer(Request $request, ExamSession $session)
    {
        $this->authorizeSession($session);

        $request->validate([
            'question_id' => 'required|exists:questions,id',
            'answer' => 'nullable',
            'is_marked_for_review' => 'boolean',
        ]);

        $answer = StudentAnswer::where('exam_session_id', $session->id)
            ->where('question_id', $request->question_id)
            ->firstOrFail();

        $normalizedAnswer = $this->normalizeAnswer($request->answer);

        $answer->update([
            'answer' => $normalizedAnswer,
            'is_answered' => $this->isAnsweredValue($normalizedAnswer),
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
            $this->authorizeSession($session);

            if ($session->status !== 'in_progress') {
                return response()->json(['error' => 'Exam already submitted'], 400);
            }

            $request->validate([
                'answers' => 'nullable|array',
                'remaining_time' => 'nullable|integer|min:0',
            ]);

            DB::beginTransaction();

            try {
                // Persist latest client-side timer snapshot before completing the session.
                if ($request->filled('remaining_time')) {
                    $session->remaining_time = max(0, (int) $request->input('remaining_time'));
                }

                // Persist all unsaved answers from client payload before grading.
                $submittedAnswers = $request->input('answers', []);
                if (is_array($submittedAnswers) && !empty($submittedAnswers)) {
                    foreach ($submittedAnswers as $questionId => $rawAnswer) {
                        $answerRow = StudentAnswer::where('exam_session_id', $session->id)
                            ->where('question_id', (int) $questionId)
                            ->first();

                        if (!$answerRow) {
                            continue;
                        }

                        $normalizedAnswer = $this->normalizeAnswer($rawAnswer);
                        $answerRow->update([
                            'answer' => $normalizedAnswer,
                            'is_answered' => $this->isAnsweredValue($normalizedAnswer),
                            'answered_at' => now(),
                        ]);
                    }
                }

                $timeRemainingAtSubmit = $request->filled('remaining_time')
                    ? max(0, (int) $request->input('remaining_time'))
                    : $this->calculateTimeRemaining($session->fresh());

                $examDurationSeconds = (int) ($session->exam->time_limit * 60);
                $timeSpent = max(0, $examDurationSeconds - $timeRemainingAtSubmit);

                // Update session first
                $session->update([
                    'status' => 'completed',
                    'submitted_at' => now(),
                    'time_spent' => $timeSpent,
                    'remaining_time' => $timeRemainingAtSubmit,
                    'last_activity_at' => now(),
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
        $this->authorizeSession($session);

        if ($session->status !== 'completed') {
            return redirect()->route('exam.session.take', $session);
        }

        $session->load(['exam', 'answers.question', 'grade']);

        return view('exam.result', compact('session'));
    }

    /**
     * Log violation (AJAX endpoint)
     */
    public function logViolation(Request $request, ExamSession $session)
    {
        $this->authorizeSession($session);

        $request->validate([
            'type' => 'required|string',
            'description' => 'required|string',
            'metadata' => 'nullable|array',
        ]);

        $violation = $session->logViolation(
            $request->type,
            $request->description,
            $request->metadata ?? []
        );

        // Pause session on focus-loss type violations
        $focusLossTypes = ['tab_switch', 'window_blur', 'fullscreen_exit', 'tab_key'];
        if (in_array($request->type, $focusLossTypes, true) && $session->status === 'in_progress') {
            $remainingFromClient = data_get($request->metadata, 'remaining_time');
            $remaining = is_numeric($remainingFromClient)
                ? max(0, (int) $remainingFromClient)
                : $this->calculateTimeRemaining($session);

            $session->update([
                'status' => 'paused',
                'remaining_time' => $remaining,
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
                'redirect' => route('dashboard'),
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
        $this->authorizeSession($session);

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
     * Sync client timer state (used during pause/resume and periodic heartbeat)
     */
    public function syncTimer(Request $request, ExamSession $session)
    {
        $this->authorizeSession($session);

        if (in_array($session->status, ['completed', 'terminated', 'expired'], true)) {
            return response()->json(['success' => true]);
        }

        $data = $request->validate([
            'remaining_time' => 'required|integer|min:0',
            'status' => 'nullable|in:in_progress,paused',
        ]);

        $update = [
            'remaining_time' => (int) $data['remaining_time'],
            'last_activity_at' => now(),
        ];

        if (!empty($data['status']) && in_array($session->status, ['scheduled', 'in_progress', 'paused'], true)) {
            $update['status'] = $data['status'];
            if ($data['status'] === 'in_progress' && !$session->started_at) {
                $update['started_at'] = now();
            }
        }

        $session->update($update);

        return response()->json([
            'success' => true,
            'time_remaining' => $this->calculateTimeRemaining($session->fresh()),
        ]);
    }


    /**
     * Calculate time remaining
     */
    private function calculateTimeRemaining(ExamSession $session)
    {
        $total = (int) ($session->exam->time_limit * 60);

        if ($session->status === 'paused') {
            if ($session->remaining_time !== null) {
                return max(0, (int) $session->remaining_time);
            }

            if (!$session->started_at) {
                return $total;
            }

            $elapsed = now()->diffInSeconds($session->started_at);
            return max(0, $total - $elapsed);
        }

        if ($session->remaining_time !== null && $session->status === 'in_progress') {
            $anchor = $session->last_activity_at ?? $session->started_at ?? now();
            $elapsedSinceAnchor = now()->diffInSeconds($anchor);
            return max(0, (int) $session->remaining_time - $elapsedSinceAnchor);
        }

        if (!$session->started_at) {
            return $session->remaining_time !== null ? max(0, (int) $session->remaining_time) : $total;
        }

        $elapsed = now()->diffInSeconds($session->started_at);
        $remaining = $total - $elapsed;

        return max(0, $remaining);
    }

    private function normalizeAnswer($answer)
    {
        if (is_array($answer)) {
            return array_values(array_filter($answer, static function ($value) {
                return $value !== null && $value !== '';
            }));
        }

        if ($answer === null) {
            return null;
        }

        if (is_string($answer)) {
            $trimmed = trim($answer);
            return $trimmed === '' ? null : $trimmed;
        }

        return $answer;
    }

    private function isAnsweredValue($answer): bool
    {
        if (is_array($answer)) {
            return count($answer) > 0;
        }

        if (is_string($answer)) {
            return trim($answer) !== '';
        }

        return $answer !== null;
    }

    /**
     * Teacher: Force end exam session
     */
    public function forceEnd(ExamSession $session)
    {
        if (!Auth::user()->isAdmin() && Auth::id() !== $session->teacher_id) {
            abort(403);
        }

        $session->update([
            'status' => 'terminated',
            'submitted_at' => now(),
        ]);

        broadcast(new ExamEnded($session, 'terminated_by_teacher'))->toOthers();
        broadcast(new \App\Events\ExamForceEnded($session))->toOthers();

        return back()->with('success', 'Exam session terminated.');
    }

    /**
     * Authorize that the current user owns this session
     */
    private function authorizeSession(ExamSession $session)
    {
        if ($session->student_id !== Auth::id() && !Auth::user()->isAdmin()) {
            abort(403, 'Unauthorized access to this exam session.');
        }
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
