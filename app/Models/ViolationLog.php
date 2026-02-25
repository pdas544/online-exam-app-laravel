<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ViolationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_session_id',
        'student_id',
        'exam_id',
        'violation_type',
        'description',
        'metadata',
        'severity',
        'auto_warned',
        'auto_terminated',
        'detected_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'auto_warned' => 'boolean',
        'auto_terminated' => 'boolean',
        'detected_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }
}