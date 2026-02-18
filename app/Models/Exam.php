<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exam extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'subject_id',
        'teacher_id',
        'academic_year',
        'semester',
        'time_limit',
        'shuffle_questions',
        'shuffle_options',
        'available_from',
        'available_to',
        'total_marks',
        'passing_marks',
        'max_attempts',
        'status',
    ];

    protected $casts = [
        'shuffle_questions' => 'boolean',
        'shuffle_options' => 'boolean',
        'available_from' => 'datetime',
        'available_to' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function questions()
    {
        return $this->belongsToMany(Question::class, 'exam_questions')
            ->withPivot('order_index', 'points_override')
            ->orderBy('exam_questions.order_index')
            ->withTimestamps();
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeActive($query)
    {
        $now = now();
        return $query->published()
            ->where('available_from', '<=', $now)
            ->where('available_to', '>=', $now);
    }

    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeForSemester($query, $year, $semester)
    {
        return $query->where('academic_year', $year)->where('semester', $semester);
    }

    // Helper methods
    public function isAvailable()
    {
        if ($this->status !== 'published') {
            return false;
        }

        $now = now();
        return (!$this->available_from || $this->available_from <= $now) &&
            (!$this->available_to || $this->available_to >= $now);
    }

    public function calculateTotalMarks()
    {
        return $this->questions()
            ->get()
            ->sum(function ($question) {
                return $question->getPointsForExam($this->id);
            });
    }

    public function updateTotalMarks()
    {
        $this->total_marks = $this->calculateTotalMarks();
        $this->save();
    }
}
