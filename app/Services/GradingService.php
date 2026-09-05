<?php

namespace App\Services;

use App\Models\ExamSession;

class GradingService
{
    /**
     * Auto-grade every answer in the session.
     * Unanswered questions score zero.
     */
    public function gradeSession(ExamSession $session): void
    {
        $session->loadMissing('answers.question');

        foreach ($session->answers as $answer) {
            if (! $answer->is_answered) {
                $answer->update([
                    'is_correct' => false,
                    'points_earned' => 0,
                ]);

                continue;
            }

            $answer->autoGrade();
        }
    }

    /**
     * @return array{earned: float, possible: float, percentage: float}
     */
    public function calculateScore(ExamSession $session): array
    {
        $earned = (float) ($session->answers()->sum('points_earned') ?: 0);
        $possible = (float) ($session->answers()->sum('max_points') ?: 0);

        return [
            'earned' => $earned,
            'possible' => $possible,
            'percentage' => $possible > 0 ? round(($earned / $possible) * 100, 2) : 0.0,
        ];
    }
}
