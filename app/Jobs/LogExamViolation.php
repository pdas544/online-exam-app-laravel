<?php

namespace App\Jobs;

use App\Events\ViolationDetected;
use App\Models\ExamSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class LogExamViolation implements ShouldQueue
{
    use Queueable;

    public int $timeout = 10;
    public int $tries = 2;

    public function __construct(
        public readonly int $sessionId,
        public readonly string $type,
        public readonly string $description,
        public readonly array $metadata = []
    ) {}

    public function handle(): void
    {
        $session = ExamSession::find($this->sessionId);

        if (!$session) {
            return;
        }

        $violation = $session->logViolation(
            $this->type,
            $this->description,
            $this->metadata
        );

        broadcast(new ViolationDetected($violation))->toOthers();
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('LogExamViolation job failed', [
            'session_id' => $this->sessionId,
            'type' => $this->type,
            'error' => $exception->getMessage(),
        ]);
    }
}
