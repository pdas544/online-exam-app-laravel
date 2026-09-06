<?php

namespace App\Console\Commands;

use App\Services\ExamSessionService;
use Illuminate\Console\Command;

class ExpireExamSessions extends Command
{
    protected $signature = 'exams:expire-sessions {--limit=100 : Maximum sessions to scan}';

    protected $description = 'Mark in-progress exam sessions past their time limit as expired';

    public function handle(ExamSessionService $sessions): int
    {
        $expired = $sessions->expireOverdue((int) $this->option('limit'));

        $this->info("Expired {$expired} session(s).");

        return self::SUCCESS;
    }
}
