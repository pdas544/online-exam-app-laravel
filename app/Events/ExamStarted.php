<?php

namespace App\Events;

use App\Models\ExamSession;

class ExamStarted extends BaseExamEvent
{
    public function __construct(ExamSession $session)
    {
        parent::__construct(
            $session->exam_id,
            $session->id,
            [
                'teacher_id' => $session->teacher_id,
                'student_name' => $session->student->name,
                'started_at' => $session->started_at,
                'time_limit' => $session->exam->time_limit,
            ]
        );
    }

    protected function getTeacherId()
    {
        return $this->data['teacher_id'] ?? null;
    }

    public function broadcastAs()
    {
        return 'exam.started';
    }
}
