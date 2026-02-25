<?php

namespace App\Events;

use App\Models\ExamSession;

class ExamEnded extends BaseExamEvent
{
    public function __construct(ExamSession $session, string $reason = 'completed')
    {
        parent::__construct(
            $session->exam_id,
            $session->id,
            [
                'student_name' => $session->student->name,
                'reason' => $reason,
                'submitted_at' => $session->submitted_at,
                'time_spent' => $session->time_spent,
            ]
        );
    }

    protected function getTeacherId()
    {
        return $this->data['teacher_id'] ?? null;
    }

    public function broadcastAs()
    {
        return 'exam.ended';
    }
}
