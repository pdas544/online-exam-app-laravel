<?php

namespace App\Jobs;

use App\Events\GradingCompleted;
use App\Models\ExamSession;
use App\Services\GradingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GradeExamSession implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(private int $sessionId)
    {
        $this->onQueue('grading');
    }

    public function handle(GradingService $grading): void
    {
        $session = ExamSession::find($this->sessionId);

        if (! $session || $session->status !== 'completed') {
            return;
        }

        $grading->gradeSession($session);
        $score = $grading->calculateScore($session);

        $percentage = round($score['percentage'], 2);
        $passed = $score['percentage'] >= ($session->exam->passing_marks ?? 40);

        $session->update([
            'score' => $percentage,
            'passed' => $passed,
        ]);

        broadcast(new GradingCompleted($session, $percentage, $passed))->toOthers();
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('grading.failed', [
            'session_id' => $this->sessionId,
            'error' => $exception->getMessage(),
        ]);
    }
}
