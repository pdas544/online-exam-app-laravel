<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class BaseExamEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $examId;
    public $sessionId;
    public $data;
    public $timestamp;

    public function __construct($examId, $sessionId = null, $data = [])
    {
        $this->examId = $examId;
        $this->sessionId = $sessionId;
        $this->data = $data;
        $this->timestamp = now()->toISOString();
    }

    public function broadcastOn()
    {
        return [
            new Channel("exam.{$this->examId}"),
            new Channel("teacher.{$this->getTeacherId()}"),
        ];
    }

    abstract protected function getTeacherId();
}
