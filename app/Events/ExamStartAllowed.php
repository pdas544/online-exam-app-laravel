<?php

namespace App\Events;

use App\Models\ExamSession;

class ExamStartAllowed extends BaseExamEvent
{
    public function __construct(ExamSession $session)
    {
        parent::__construct(
            $session->exam_id,
            $session->id,
            [
                'student_id' => $session->student_id,
                'teacher_id' => $session->teacher_id,
                'message' => 'You may start the exam now.',
            ]
        );
    }

    protected function getTeacherId()
    {
        return $this->data['teacher_id'] ?? null;
    }

    public function broadcastAs()
    {
        return 'exam.start.allowed';
    }
}
