<?php

namespace App\Jobs;

use App\Events\ExamEnded;
use App\Models\ExamSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GradeExamSession implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;
    public int $tries = 1;

    public function __construct(public readonly int $sessionId) {}

    public function handle(): void
    {
        $session = ExamSession::with(['answers.question', 'exam', 'student'])->find($this->sessionId);

        if (!$session || $session->status !== 'completed') {
            return;
        }

        // Skip re-grading if score already set
        if ($session->score !== null) {
            return;
        }

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

        // Reload to get fresh sum after grading
        $totalEarned = $session->answers()->sum('points_earned') ?: 0;
        $totalPossible = $session->answers()->sum('max_points') ?: 1;
        $score = ($totalEarned / $totalPossible) * 100;

        $session->update([
            'score' => round($score, 2),
            'passed' => $score >= ($session->exam->passing_marks ?? 40),
        ]);

        broadcast(new ExamEnded($session->fresh()->load('student'), 'completed'));

        Log::info('Exam graded', [
            'session_id' => $session->id,
            'score' => round($score, 2),
            'student_id' => $session->student_id,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GradeExamSession job failed', [
            'session_id' => $this->sessionId,
            'error' => $exception->getMessage(),
        ]);
    }
}
