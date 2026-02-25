<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExamResumed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $sessionId;
    public int $studentId;
    public string $message;

    public function __construct(int $sessionId, int $studentId, string $message = 'Resume allowed')
    {
        $this->sessionId = $sessionId;
        $this->studentId = $studentId;
        $this->message = $message;
    }

    public function broadcastOn()
    {
        return [new Channel("student.{$this->studentId}")];
    }

    public function broadcastAs()
    {
        return 'exam.resume';
    }
}
