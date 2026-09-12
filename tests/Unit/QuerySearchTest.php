<?php

namespace Tests\Unit;

use App\Models\Subject;
use App\Support\QuerySearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuerySearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_contains_generates_lower_like_with_bound_lowercased_pattern(): void
    {
        $query = QuerySearch::contains(Subject::query(), 'name', 'ComPuter');

        $this->assertStringContainsString('LOWER(name) LIKE ?', $query->toSql());
        $this->assertContains('%computer%', $query->getBindings());
    }

    public function test_or_contains_uses_or_where(): void
    {
        $query = Subject::query()->where('id', '>', 0);
        QuerySearch::orContains($query, 'name', 'x');

        $this->assertStringContainsString('or LOWER(name) LIKE ?', $query->toSql());
    }

    public function test_wildcards_in_term_are_escaped(): void
    {
        $bindings = QuerySearch::contains(Subject::query(), 'name', '100%_off')->getBindings();

        $this->assertCount(1, $bindings);
        $this->assertTrue(str_starts_with($bindings[0], '%'));
        $this->assertTrue(str_ends_with($bindings[0], '%'));
        $this->assertStringContainsString('\%', $bindings[0]);
        $this->assertStringContainsString('\_', $bindings[0]);
    }

    public function test_escaped_wildcards_match_literally(): void
    {
        Subject::factory()->create(['name' => '100% coverage']);
        Subject::factory()->create(['name' => '100X coverage']);

        $matches = Subject::query()
            ->tap(fn ($q) => QuerySearch::contains($q, 'name', '100%'))
            ->pluck('name')
            ->all();

        $this->assertContains('100% coverage', $matches);
        $this->assertNotContains('100X coverage', $matches);
    }

    public function test_unicode_term_is_lowercased(): void
    {
        $query = QuerySearch::contains(Subject::query(), 'name', 'ÄPFEL');

        $this->assertContains('%äpfel%', $query->getBindings());
    }

    public function test_end_to_end_case_insensitive_match(): void
    {
        Subject::factory()->create(['name' => 'Computer Science']);

        $found = Subject::query()
            ->tap(fn ($q) => QuerySearch::contains($q, 'name', 'computer'))
            ->exists();
        $notFound = Subject::query()
            ->tap(fn ($q) => QuerySearch::contains($q, 'name', 'biology'))
            ->exists();

        $this->assertTrue($found);
        $this->assertFalse($notFound);
    }
}
