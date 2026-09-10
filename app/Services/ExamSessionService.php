<?php

namespace App\Services;

use App\Jobs\GradeExamSession;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\StudentAnswer;
use DomainException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ExamSessionService
{
    private const START_LOCK_TTL = 10;

    private const SUBMIT_LOCK_TTL = 10;

    private const GRADING_REDISPATCH_AFTER_MINUTES = 15;

    public static function startLockKey(int $examId, int $studentId): string
    {
        return "exam-start:{$examId}:{$studentId}";
    }

    public static function submitLockKey(int $sessionId): string
    {
        return "exam-submit:{$sessionId}";
    }

    /**
     * Lock wait in seconds. Tunable via `exam.start_lock_wait` (default 5);
     * tests lower it so contention tests fail fast instead of sleeping.
     */
    private function lockWaitSeconds(): int
    {
        return max(1, (int) config('exam.start_lock_wait', 5));
    }

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

        return Cache::lock(static::startLockKey($exam->id, $studentId), self::START_LOCK_TTL)
            ->block($this->lockWaitSeconds(), function () use ($exam, $studentId) {
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
                    $exam->loadMissing(['questions' => fn ($query) => $query->orderBy('order_index')]);
                    $questions = $exam->questions;

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
    /**
     * Submit an in-progress session: mark completed and dispatch async grading.
     * Idempotent — resubmitting a completed session returns its stored result,
     * re-dispatching grading when a previous attempt never finished.
     *
     * @return array{percentage: float, passed: bool, grading_pending: bool}
     *
     * @throws DomainException when the session is not submittable.
     */
    public function submit(ExamSession $session): array
    {
        return Cache::lock(static::submitLockKey($session->id), self::SUBMIT_LOCK_TTL)
            ->block($this->lockWaitSeconds(), fn () => $this->submitOnce($session->fresh()));
    }

    private function submitOnce(ExamSession $session): array
    {
        if ($session->status === 'completed') {
            if ($session->score !== null) {
                return [
                    'percentage' => (float) $session->score,
                    'passed' => (bool) $session->passed,
                    'grading_pending' => false,
                ];
            }

            // Completed but ungraded: re-dispatch only when no grading job was
            // queued recently (worker-down recovery), so submit bursts and
            // double-clicks don't pile duplicate grading jobs.
            if ($this->gradingDispatchStale($session)) {
                $this->dispatchGrading($session);
            }

            return ['percentage' => 0.0, 'passed' => false, 'grading_pending' => true];
        }

        if ($session->status !== 'in_progress') {
            throw new DomainException('This exam session cannot be submitted.');
        }

        DB::transaction(function () use ($session) {
            $timeSpent = $session->started_at
                ? abs((int) $session->started_at->diffInSeconds(now(), false))
                : 0;

            // Stamp the grading dispatch in the same write so a fresh submit
            // costs no extra query; re-dispatches only ever touch this column.
            $session->update([
                'status' => 'completed',
                'submitted_at' => now(),
                'time_spent' => $timeSpent,
                'grading_dispatched_at' => now(),
            ]);
        });

        GradeExamSession::dispatch($session->id);

        return ['percentage' => 0.0, 'passed' => false, 'grading_pending' => true];
    }

    private function gradingDispatchStale(ExamSession $session): bool
    {
        $dispatchedAt = $session->grading_dispatched_at;

        return $dispatchedAt === null
            || $dispatchedAt->lt(now()->subMinutes(self::GRADING_REDISPATCH_AFTER_MINUTES));
    }

    private function dispatchGrading(ExamSession $session): void
    {
        GradeExamSession::dispatch($session->id);
        $session->update(['grading_dispatched_at' => now()]);
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
