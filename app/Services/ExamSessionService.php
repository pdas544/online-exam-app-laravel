<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\StudentAnswer;
use DomainException;
use Illuminate\Support\Facades\DB;

class ExamSessionService
{
    public function __construct(private GradingService $grading) {}

    /**
     * Start (or resume) an exam for a student.
     *
     * Returns the existing active session when one is already open.
     *
     * @throws DomainException when the exam is unavailable or attempts are exhausted.
     */
    public function start(Exam $exam, int $studentId): ExamSession
    {
        if (! $exam->isAvailable()) {
            throw new DomainException('This exam is not available at this time.');
        }

        $active = ExamSession::where('exam_id', $exam->id)
            ->where('student_id', $studentId)
            ->whereIn('status', ['scheduled', 'in_progress', 'paused'])
            ->first();

        if ($active) {
            return $active;
        }

        $completedAttempts = ExamSession::where('exam_id', $exam->id)
            ->where('student_id', $studentId)
            ->where('status', 'completed')
            ->count();

        if ($completedAttempts >= ($exam->max_attempts ?? 1)) {
            throw new DomainException('You have already completed this exam.');
        }

        return DB::transaction(function () use ($exam, $studentId) {
            $questions = $exam->questions()->orderBy('order_index')->get();

            $session = ExamSession::create([
                'exam_id' => $exam->id,
                'student_id' => $studentId,
                'teacher_id' => $exam->teacher_id,
                'status' => 'scheduled',
                'started_at' => null,
                'total_questions' => $questions->count(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            foreach ($questions as $question) {
                StudentAnswer::create([
                    'exam_session_id' => $session->id,
                    'question_id' => $question->id,
                    'exam_id' => $exam->id,
                    'max_points' => $question->pivot->points_override ?? $question->points,
                ]);
            }

            return $session;
        });
    }

    /**
     * Submit an in-progress session: grade, score, complete.
     * Idempotent — resubmitting a completed session returns its stored result.
     *
     * @return array{percentage: float, passed: bool}
     *
     * @throws DomainException when the session is not submittable.
     */
    public function submit(ExamSession $session): array
    {
        if ($session->status === 'completed') {
            return [
                'percentage' => (float) $session->score,
                'passed' => (bool) $session->passed,
            ];
        }

        if ($session->status !== 'in_progress') {
            throw new DomainException('This exam session cannot be submitted.');
        }

        return DB::transaction(function () use ($session) {
            $timeSpent = $session->started_at
                ? abs((int) $session->started_at->diffInSeconds(now(), false))
                : 0;

            $session->update([
                'status' => 'completed',
                'submitted_at' => now(),
                'time_spent' => $timeSpent,
            ]);

            $this->grading->gradeSession($session);
            $score = $this->grading->calculateScore($session);

            $session->update([
                'score' => round($score['percentage'], 2),
                'passed' => $score['percentage'] >= ($session->exam->passing_marks ?? 40),
            ]);

            return [
                'percentage' => (float) $session->fresh()->score,
                'passed' => (bool) $session->fresh()->passed,
            ];
        });
    }

    /**
     * Begin the attempt: persist scheduled → in_progress and start the clock.
     * No-op for sessions already underway; rejects terminal sessions.
     *
     * @throws DomainException when the session can no longer be taken.
     */
    public function begin(ExamSession $session): ExamSession
    {
        if (in_array($session->status, ['completed', 'terminated', 'expired'], true)) {
            throw new DomainException('This exam session can no longer be taken.');
        }

        if ($session->status === 'scheduled') {
            $session->update([
                'status' => 'in_progress',
                'started_at' => $session->started_at ?? now(),
            ]);
        }

        return $session->fresh();
    }

    public function forceEnd(ExamSession $session): void
    {
        if (! in_array($session->status, ['scheduled', 'in_progress', 'paused'], true)) {
            throw new DomainException('Only an active exam session can be terminated.');
        }

        $session->update([
            'status' => 'terminated',
            'submitted_at' => now(),
        ]);
    }

    /**
     * Resume a paused session: shift the clock forward by the paused
     * duration so the timer continues where it left off.
     *
     * @throws DomainException when the session is not paused.
     */
    public function resume(ExamSession $session): ExamSession
    {
        if ($session->status !== 'paused') {
            throw new DomainException('Only a paused exam session can be resumed.');
        }

        $pausedSeconds = $session->paused_at
            ? (int) $session->paused_at->diffInSeconds(now(), true)
            : 0;

        $session->update([
            'status' => 'in_progress',
            'started_at' => ($session->started_at ?? now())->addSeconds($pausedSeconds),
            'paused_at' => null,
            'last_activity_at' => now(),
        ]);

        return $session->fresh();
    }

    /**
     * Mark in-progress sessions past their time limit as expired.
     * Returns the number of sessions expired.
     */
    public function expireOverdue(?int $limit = 100): int
    {
        $expired = 0;

        ExamSession::with('exam')
            ->where('status', 'in_progress')
            ->whereNotNull('started_at')
            ->limit($limit)
            ->chunkById(50, function ($sessions) use (&$expired) {
                foreach ($sessions as $session) {
                    if ($session->timeRemaining() <= 0) {
                        $session->update([
                            'status' => 'expired',
                            'submitted_at' => now(),
                        ]);
                        $expired++;
                    }
                }
            });

        return $expired;
    }
}
