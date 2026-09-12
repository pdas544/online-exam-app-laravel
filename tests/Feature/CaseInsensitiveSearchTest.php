<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * User-facing search is case-insensitive on every driver.
 *
 * NOTE on what these tests can and cannot prove: sqlite `LIKE` is already
 * ASCII case-insensitive, so these behavior tests pass with EITHER `like`
 * or the `QuerySearch` helper on sqlite — they lock the behavior, but the
 * prod (pgsql) regression is locked by Tests\Unit\QuerySearchTest, which
 * asserts on the generated SQL and bindings instead.
 */
class CaseInsensitiveSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $admin;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->subject = Subject::factory()->create([
            'name' => 'Computer Science',
            'created_by' => $this->teacher->id,
        ]);
    }

    public function test_subject_search_matches_mixed_case(): void
    {
        Subject::factory()->create(['name' => 'Biology', 'created_by' => $this->teacher->id]);

        $response = $this->actingAs($this->admin)->get('/subjects?search=computer');

        $response->assertOk()
            ->assertSee('Computer Science')
            ->assertDontSee('Biology');
    }

    public function test_user_search_matches_mixed_case_name_and_email(): void
    {
        User::factory()->create([
            'name' => 'Grace Hopper',
            'email' => 'grace.hopper@example.com',
            'role' => 'teacher',
        ]);

        $this->actingAs($this->admin)->get('/users?search=grace')
            ->assertOk()->assertSee('Grace Hopper');

        $this->actingAs($this->admin)->get('/users?search=HOPPER')
            ->assertOk()->assertSee('Grace Hopper');

        $this->actingAs($this->admin)->get('/users?search=GRACE.HOPPER@EXAMPLE')
            ->assertOk()->assertSee('Grace Hopper');
    }

    public function test_question_search_matches_mixed_case(): void
    {
        Question::factory()->create([
            'subject_id' => $this->subject->id,
            'created_by' => $this->teacher->id,
            'question_text' => 'Explain the CPU cache hierarchy in detail?',
            'question_type' => 'fill_blank',
            'correct_answers' => ['sram'],
        ]);

        $response = $this->actingAs($this->teacher)->get('/questions?search=cpu CACHE');

        $response->assertOk()->assertSee('CPU cache', false);
    }

    public function test_exam_search_matches_mixed_case_title(): void
    {
        Exam::factory()->create([
            'subject_id' => $this->subject->id,
            'teacher_id' => $this->teacher->id,
            'title' => 'Midterm Examination',
            'status' => 'published',
            'available_from' => null,
            'available_to' => null,
        ]);

        $response = $this->actingAs($this->teacher)->get('/exams?search=midterm');

        $response->assertOk()->assertSee('Midterm Examination');
    }
}
