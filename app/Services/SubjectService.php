<?php

namespace App\Services;

use App\Models\Subject;
use Illuminate\Support\Facades\Cache;

class SubjectService
{
    private const CACHE_KEY = 'subjects.all';
    private const CACHE_TTL_SECONDS = 3600;

    public function getAllSubjects()
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            return Subject::select('id', 'name', 'description', 'created_by')
                ->orderBy('name')
                ->get();
        });
    }

    public function invalidateCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
