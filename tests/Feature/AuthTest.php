<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'student@example.com',
            'password' => bcrypt('password123'),
            'role' => 'student',
        ]);

        $this->post('/login', ['email' => 'student@example.com', 'password' => 'password123'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rejects_bad_password(): void
    {
        User::factory()->create(['email' => 'student@example.com', 'password' => bcrypt('password123')]);

        $this->post('/login', ['email' => 'student@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors();

        $this->assertGuest();
    }

    public function test_register_creates_student_and_logs_in(): void
    {
        $this->post('/register', [
            'name' => 'New Student',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'student',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'role' => 'student']);
        $this->assertAuthenticated();
    }

    public function test_register_rejects_teacher_escalation_outside_allowlist(): void
    {
        $this->post('/register', [
            'name' => 'Sneaky Admin',
            'email' => 'sneaky@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_register_rejects_teacher_role(): void
    {
        // Teachers are created by admins, never via self-registration.
        $this->post('/register', [
            'name' => 'Sneaky Teacher',
            'email' => 'sneaky@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'teacher',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_logout_ends_session(): void
    {
        $user = User::factory()->create(['role' => 'student']);

        $this->actingAs($user)->post('/logout')->assertRedirect();

        $this->assertGuest();
    }

    public function test_guest_cannot_open_dashboards(): void
    {
        $this->get('/student/dashboard')->assertRedirect('/login');
        $this->get('/teacher/dashboard')->assertRedirect('/login');
    }
}
