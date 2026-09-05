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

        $studentAnswers = $this->normalizeStoredValues($this->answer);
        $normalizedCorrect = $this->normalizeStoredValues($this->question->correct_answers);

        switch ($this->question->question_type) {
            case 'mcq_single':
            case 'true_false':
                if ($this->question->question_type === 'true_false') {
                    $studentAnswers = array_map([$this, 'normalizeTrueFalseToken'], $studentAnswers);
                    $normalizedCorrect = array_map([$this, 'normalizeTrueFalseToken'], $normalizedCorrect);
                }

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

    private function normalizeStoredValues(mixed $value): array
    {
        // Handle legacy rows where JSON was double encoded as a string.
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;

                if (is_string($value)) {
                    $secondDecode = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $secondDecode;
                    }
                }
            }
        }

        if ($value === null) {
            return [];
        }

        $items = is_array($value) ? $value : [$value];

        $items = array_values(array_filter($items, static function ($item) {
            if ($item === null) {
                return false;
            }

            return trim((string) $item) !== '';
        }));

        return array_map(static function ($item) {
            return trim((string) $item);
        }, $items);
    }

    private function normalizeTrueFalseToken(mixed $value): string
    {
        $token = strtolower(trim((string) $value));

        if (in_array($token, ['1', 'true', 'yes'], true)) {
            return 'true';
        }

        if (in_array($token, ['0', 'false', 'no'], true)) {
            return 'false';
        }

        return $token;
    }
}
