<?php

namespace App\Services;

use App\Models\Exam;
use Illuminate\Support\Facades\Cache;

class ExamService
{
    private const CACHE_TTL_SECONDS = 600;

    public function getExamWithQuestions(Exam $exam): Exam
    {
        $cacheKey = $this->cacheKey($exam->id);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($exam) {
            return $exam->load([
                'subject:id,name',
                'teacher:id,name',
                'questions' => function ($query) {
                    $query->orderBy('exam_questions.order_index');
                },
            ]);
        });
    }

    public function invalidateExamCache(Exam $exam): void
    {
        Cache::forget($this->cacheKey($exam->id));
    }

    private function cacheKey(int $examId): string
    {
        return "exam.{$examId}.with-questions";
    }
}
