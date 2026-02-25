<?php

namespace App\Events;

use App\Models\StudentAnswer;
use Illuminate\Broadcasting\Channel;

class AnswerSaved extends BaseExamEvent
{
    public function __construct(StudentAnswer $answer)
    {
        parent::__construct(
            $answer->exam_id,
            $answer->exam_session_id,
            [
                'question_id' => $answer->question_id,
                'is_answered' => $answer->is_answered,
                'is_marked_for_review' => $answer->is_marked_for_review,
                'answered_at' => $answer->answered_at,
            ]
        );
    }

    protected function getTeacherId()
    {
        // Teachers don't need real-time answer updates (privacy)
        return null;
    }

    public function broadcastAs()
    {
        return 'answer.saved';
    }

    public function broadcastOn()
    {
        // Only broadcast to the specific student's private channel
        return [new Channel("student.{$this->getStudentId()}")];
    }

    private function getStudentId()
    {
        // You'll need to implement this based on your session data
        return $this->data['student_id'] ?? null;
    }
}
