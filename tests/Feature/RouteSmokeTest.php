<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
        $this->get(route('teacher.dashboard'))->assertRedirect(route('login'));
        $this->get(route('student.dashboard'))->assertRedirect(route('login'));
        $this->get(route('teacher.monitor'))->assertRedirect(route('login'));
    }

    public function test_admin_dashboard_loads_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->getJson(route('admin.metrics'))->assertOk();
    }

    public function test_role_dashboards_load_for_owners(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($teacher)->get(route('teacher.dashboard'))->assertOk();
        $this->actingAs($teacher)->get(route('teacher.monitor'))->assertOk();
        $this->actingAs($student)->get(route('student.dashboard'))->assertOk();
        $this->actingAs($student)->get(route('student.results.index'))->assertOk();
    }

    public function test_resource_indexes_load(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);

        $this->actingAs($teacher)->get(route('exams.index'))->assertOk();
        $this->actingAs($teacher)->get(route('questions.index'))->assertOk();
        $this->actingAs($teacher)->get(route('subjects.index'))->assertOk();
    }
}
