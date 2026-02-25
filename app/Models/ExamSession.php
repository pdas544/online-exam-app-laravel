<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'student_id',
        'teacher_id',
        'status',
        'started_at',
        'submitted_at',
        'time_spent',
        'remaining_time',
        'current_question_index',
        'total_questions',
        'answered_questions',
        'violation_count',
        'fullscreen_enabled',
        'last_activity_at',
        'ip_address',
        'user_agent',
        'score',
        'passed',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'answered_questions' => 'array',
        'time_spent' => 'integer',
        'remaining_time' => 'integer',
        'violation_count' => 'integer',
        'score' => 'decimal:2',
        'passed' => 'boolean',
        'fullscreen_enabled' => 'boolean',
    ];

    // Relationships
    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function answers()
    {
        return $this->hasMany(StudentAnswer::class);
    }

    public function violations()
    {
        return $this->hasMany(ViolationLog::class);
    }

    public function grade()
    {
        return $this->hasOne(Grade::class, 'exam_session_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['in_progress', 'paused']);
    }

    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    // Helper Methods
    public function isActive(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function timeRemaining(): int
    {
        if (!$this->exam || !$this->started_at) {
            return 0;
        }

        $totalSeconds = $this->exam->time_limit * 60;
        $elapsedSeconds = now()->diffInSeconds($this->started_at);
        return max(0, $totalSeconds - $elapsedSeconds);
    }

    public function updateProgress(): void
    {
        $answered = $this->answers()->where('is_answered', true)->count();
        $this->current_question_index = $answered;
        $this->answered_questions = $this->answers()
            ->where('is_answered', true)
            ->pluck('question_id')
            ->toArray();
        $this->save();
    }

    public function logViolation(string $type, string $description, array $metadata = []): ViolationLog
    {
        $violation = $this->violations()->create([
            'student_id' => $this->student_id,
            'exam_id' => $this->exam_id,
            'violation_type' => $type,
            'description' => $description,
            'metadata' => $metadata,
            'severity' => $this->calculateSeverity($type),
        ]);

        $this->increment('violation_count');

        // Auto-terminate after 5 violations
        if ($this->violation_count >= 5) {
            $this->status = 'terminated';
            $this->save();
            $violation->update(['auto_terminated' => true]);
        }

        return $violation;
    }

    private function calculateSeverity(string $type): int
    {
        return match($type) {
            'tab_switch', 'window_blur' => 1,
            'fullscreen_exit' => 2,
            'copy_attempt', 'paste_attempt' => 3,
            'multiple_ips', 'time_manipulation' => 4,
            'suspicious_activity' => 5,
            default => 1,
        };
    }
}
