<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Portable case-insensitive substring search.
 *
 * Postgres `LIKE` is case-sensitive while sqlite `LIKE` is ASCII
 * case-insensitive, so bare `like` behaves differently in prod vs tests
 * (and `ilike` fatals sqlite). `LOWER(col) LIKE` is standard SQL that
 * behaves identically on both drivers.
 *
 * NOTE: this deliberately diverges from the db-compat skill's "bare like is
 * close enough" guidance — that tradeoff is what caused prod search
 * ('computer' vs 'Computer') to silently disagree with the test suite.
 */
class QuerySearch
{
    /**
     * Add a case-insensitive "contains" filter.
     *
     * @param  string  $column  Trusted column reference (e.g. 'name' or 'exams.title'). Never pass user input here.
     */
    public static function contains(EloquentBuilder|QueryBuilder $query, string $column, string $term): EloquentBuilder|QueryBuilder
    {
        return $query->whereRaw("LOWER({$column}) LIKE ? ESCAPE '\\'", [self::pattern($term)]);
    }

    /**
     * OR variant of {@see contains()}, for use inside grouped closures.
     */
    public static function orContains(EloquentBuilder|QueryBuilder $query, string $column, string $term): EloquentBuilder|QueryBuilder
    {
        return $query->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '\\'", [self::pattern($term)]);
    }

    private static function pattern(string $term): string
    {
        return '%'.self::escapeWildcards(mb_strtolower($term, 'UTF-8')).'%';
    }

    /**
     * Escape LIKE wildcards so user input matches literally.
     */
    private static function escapeWildcards(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);
    }
}
