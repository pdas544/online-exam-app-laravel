<?php

namespace App\Jobs;

use App\Events\ViolationDetected;
use App\Models\ExamSession;
use App\Services\ViolationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class LogExamViolation implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $sessionId,
        public readonly string $type,
        public readonly string $description,
        public readonly array $metadata = [],
    ) {
        $this->onQueue('violations');
    }

    public function handle(ViolationService $violations): void
    {
        $session = ExamSession::find($this->sessionId);

        if (! $session) {
            return;
        }

        $violation = $violations->record(
            $session,
            $this->type,
            $this->description,
            $this->metadata
        );

        $violations->pauseOnFocusLoss($session, $this->type);

        broadcast(new ViolationDetected($violation))->toOthers();
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('violation.failed', [
            'session_id' => $this->sessionId,
            'type' => $this->type,
            'error' => $exception->getMessage(),
        ]);
    }
}
