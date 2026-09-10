<?php

namespace App\Events;

use App\Models\ExamSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GradingCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $broadcastQueue = 'broadcasts';

    public int $sessionId;

    public int $studentId;

    public int $examId;

    public float $score;

    public bool $passed;

    public function __construct(ExamSession $session, float $score, bool $passed)
    {
        $this->sessionId = $session->id;
        $this->studentId = $session->student_id;
        $this->examId = $session->exam_id;
        $this->score = $score;
        $this->passed = $passed;
    }

    public function broadcastOn()
    {
        return [new PrivateChannel("student.{$this->studentId}")];
    }

    public function broadcastAs()
    {
        return 'grading.completed';
    }
}
