<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class R1R2SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_is_forbidden_from_exam_index(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)->get('/exams')->assertForbidden();
    }

    public function test_teacher_can_access_exam_index(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)->get('/exams')->assertOk();
    }

    public function test_admin_can_access_users_index(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/users')->assertOk();
    }

    public function test_teacher_is_forbidden_from_users_index(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)->get('/users')->assertForbidden();
    }

    public function test_register_validation_still_rejects_bad_input(): void
    {
        $this->post('/register', [])->assertSessionHasErrors(['name', 'email', 'password', 'role']);
    }

    public function test_student_cannot_view_another_teachers_question(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $question = \App\Models\Question::factory()->create(['created_by' => $teacher->id]);

        $this->actingAs($student)->get("/questions/{$question->id}")->assertForbidden();
    }
}
