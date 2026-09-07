<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Subject|null $subject
 * @property-read User|null $teacher
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Question> $questions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ExamSession> $sessions
 * @property int|null $questions_count Set by withCount('questions').
 */
class Exam extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'instructions',
        'instructions_file',
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
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'exam_questions')
            ->withPivot('order_index', 'points_override')
            ->orderBy('exam_questions.order_index')
            ->withTimestamps();
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ExamSession::class);
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
        $total = 0;

        foreach ($this->questions()->get() as $question) {
            if ($question instanceof Question) {
                $total += $question->getPointsForExam($this->id);
            }
        }

        return $total;
    }

    public function updateTotalMarks()
    {
        $this->total_marks = $this->calculateTotalMarks();
        $this->save();
    }
}
