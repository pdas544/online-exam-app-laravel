<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_session_id',
        'question_id',
        'exam_id',
        'answer',
        'is_answered',
        'is_marked_for_review',
        'is_correct',
        'points_earned',
        'max_points',
        'time_spent',
        'answered_at',
    ];

    protected $casts = [
        'answer' => 'array',
        'is_answered' => 'boolean',
        'is_marked_for_review' => 'boolean',
        'is_correct' => 'boolean',
        'points_earned' => 'decimal:2',
        'max_points' => 'decimal:2',
        'answered_at' => 'datetime',
    ];

    // Relationships
    public function session()
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    // Scopes
    public function scopeCorrect($query)
    {
        return $query->where('is_correct', true);
    }

    public function scopeIncorrect($query)
    {
        return $query->where('is_correct', false);
    }

    public function scopeMarkedForReview($query)
    {
        return $query->where('is_marked_for_review', true);
    }

    // Helper Methods
    public function autoGrade(): void
    {
        if (!$this->question) {
            return;
        }

        $correctAnswers = (array) ($this->question->correct_answers ?? []);
        $studentAnswer = $this->answer;

        $studentAnswers = is_array($studentAnswer)
            ? $studentAnswer
            : ($studentAnswer === null ? [] : [$studentAnswer]);

        $studentAnswers = array_values(array_filter($studentAnswers, static function ($value) {
            return $value !== null && $value !== '';
        }));

        $normalizedCorrect = array_values(array_filter($correctAnswers, static function ($value) {
            return $value !== null && $value !== '';
        }));

        switch ($this->question->question_type) {
            case 'mcq_single':
            case 'true_false':
                $studentValue = isset($studentAnswers[0]) ? (string) $studentAnswers[0] : null;
                $correctValue = isset($normalizedCorrect[0]) ? (string) $normalizedCorrect[0] : null;
                $this->is_correct = $studentValue !== null && $correctValue !== null && $studentValue === $correctValue;
                break;

            case 'mcq_multiple':
                sort($studentAnswers);
                sort($normalizedCorrect);
                $this->is_correct = $studentAnswers == $normalizedCorrect;
                break;

            case 'fill_blank':
                $studentValue = strtolower(trim((string) ($studentAnswers[0] ?? '')));
                $normalizedCorrect = array_map(static function ($answer) {
                    return strtolower(trim((string) $answer));
                }, $normalizedCorrect);
                $this->is_correct = $studentValue !== '' && in_array($studentValue, $normalizedCorrect, true);
                break;

            default:
                $this->is_correct = false;
                break;
        }

        $this->points_earned = $this->is_correct ? $this->max_points : 0;
        $this->save();
    }
}
