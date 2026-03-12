<?php

namespace App\Events;

use App\Models\ExamSession;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExamForceEnded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $sessionId;
    public int $studentId;
    public string $message;
    public string $redirect;

    public function __construct(ExamSession $session, string $message = 'Your exam was ended by the Admin', string $redirect = '/student/dashboard?ended=1')
    {
        $this->sessionId = $session->id;
        $this->studentId = $session->student_id;
        $this->message = $message;
        $this->redirect = $redirect;
    }

    public function broadcastOn()
    {
        return [new Channel("student.{$this->studentId}")];
    }

    public function broadcastAs()
    {
        return 'exam.forceEnd';
    }
}
