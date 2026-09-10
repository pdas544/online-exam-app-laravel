<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class BaseExamEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Dedicated queue so realtime fan-out never head-blocks grading or
     * violation jobs on a shared worker.
     */
    public $broadcastQueue = 'broadcasts';

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
            new PrivateChannel("exam.{$this->examId}"),
            new PrivateChannel("teacher.{$this->getTeacherId()}"),
        ];
    }

    abstract protected function getTeacherId();
}
