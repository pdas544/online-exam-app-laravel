<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read User|null $creator
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Question> $questions
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Exam> $exams
 */
class Subject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    // Scope for teachers to see only their subjects
    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('created_by', $teacherId);
    }

    public function examsBySemester($year, $semester)
    {
        return $this->exams()->where('year', $year)->where('semester', $semester)->get();
    }
}
