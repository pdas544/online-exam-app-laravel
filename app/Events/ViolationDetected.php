<?php

namespace App\Events;

use App\Models\ViolationLog;

class ViolationDetected extends BaseExamEvent
{
    public function __construct(ViolationLog $violation)
    {
        parent::__construct(
            $violation->exam_id,
            $violation->exam_session_id,
            [
                'teacher_id' => $violation->session?->teacher_id,
                'student_name' => $violation->student->name,
                'violation_type' => $violation->violation_type,
                'severity' => $violation->severity,
                'description' => $violation->description,
                'auto_terminated' => $violation->auto_terminated,
            ]
        );
    }

    protected function getTeacherId()
    {
        return $this->data['teacher_id'] ?? null;
    }

    public function broadcastAs()
    {
        return 'violation.detected';
    }
}
