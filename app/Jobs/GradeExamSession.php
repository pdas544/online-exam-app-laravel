<?php

namespace App\Jobs;

use App\Models\ExamSession;
use App\Services\GradingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GradeExamSession implements ShouldQueue
{
    use Queueable;

    public function __construct(private int $sessionId) {}

    public function handle(GradingService $grading): void
    {
        $session = ExamSession::find($this->sessionId);

        if (! $session || $session->status !== 'completed') {
            return;
        }

        $grading->gradeSession($session);
        $score = $grading->calculateScore($session);

        $session->update([
            'score' => round($score['percentage'], 2),
            'passed' => $score['percentage'] >= ($session->exam->passing_marks ?? 40),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('grading.failed', [
            'session_id' => $this->sessionId,
            'error' => $exception->getMessage(),
        ]);
    }
}
