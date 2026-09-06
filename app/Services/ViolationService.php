<?php

namespace App\Services;

use App\Models\ExamSession;
use App\Models\ViolationLog;

class ViolationService
{
    private const FOCUS_LOSS_TYPES = ['tab_switch', 'window_blur', 'fullscreen_exit', 'tab_key'];

    /**
     * Record a violation: persists the log, bumps the counter,
     * auto-terminates the session once the threshold is reached.
     */
    public function record(
        ExamSession $session,
        string $type,
        string $description,
        array $metadata = []
    ): ViolationLog {
        return $session->logViolation($type, $description, $metadata);
    }

    /**
     * Pause an in-progress session on focus-loss violations.
     * Returns true when the session was paused.
     */
    public function pauseOnFocusLoss(ExamSession $session, string $type): bool
    {
        if (! in_array($type, self::FOCUS_LOSS_TYPES, true)) {
            return false;
        }

        if ($session->status !== 'in_progress') {
            return false;
        }

        $session->update([
            'status' => 'paused',
            'paused_at' => now(),
            'last_activity_at' => now(),
        ]);

        return true;
    }
}
