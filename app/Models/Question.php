<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'subject_id',
        'question_text',
        'question_type',
        'options',
        'correct_answers',
        'points',
        'explanation',
        'created_by',
    ];

    protected $casts = [
        'options' => 'array',
        'correct_answers' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function exams()
    {
        return $this->belongsToMany(Exam::class, 'exam_questions')
            ->withPivot('order_index', 'points_override')
            ->withTimestamps();
    }

    // Helper methods
    public function isMultipleChoice()
    {
        return in_array($this->question_type, ['mcq_single', 'mcq_multiple']);
    }

    public function isTrueFalse()
    {
        return $this->question_type === 'true_false';
    }

    public function isFillInBlank()
    {
        return $this->question_type === 'fill_blank';
    }

    public function getPointsForExam($examId)
    {
        $examQuestion = $this->exams()->where('exam_id', $examId)->first();
        return $examQuestion->pivot->points_override ?? $this->points;
    }
}
