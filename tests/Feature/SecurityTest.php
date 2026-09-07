<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited(): void
    {
        config()->set('rate_limits.auth', 2);
        User::factory()->create(['email' => 'u@example.com', 'password' => bcrypt('password123')]);

        $this->post('/login', ['email' => 'u@example.com', 'password' => 'wrong']);
        $this->post('/login', ['email' => 'u@example.com', 'password' => 'wrong']);
        $this->post('/login', ['email' => 'u@example.com', 'password' => 'wrong'])
            ->assertStatus(429);
    }

    public function test_exam_endpoints_are_rate_limited(): void
    {
        config()->set('rate_limits.exam', 2);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $subject = \App\Models\Subject::factory()->create(['created_by' => $teacher->id]);
        $exam = \App\Models\Exam::factory()->create([
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'status' => 'published',
            'available_from' => null,
            'available_to' => null,
        ]);
        $session = \App\Models\ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'total_questions' => 0,
        ]);

        $url = route('exam.session.status', $session);
        $this->actingAs($student)->get($url);
        $this->actingAs($student)->get($url);
        $this->actingAs($student)->get($url)->assertStatus(429);
    }

    public function test_register_rejects_weak_password(): void
    {
        $this->post('/register', [
            'name' => 'Weak',
            'email' => 'weak@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'student',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'weak@example.com']);
    }

    public function test_security_headers_present(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_custom_forbidden_page_renders(): void
    {
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student)
            ->get('/teacher/dashboard')
            ->assertForbidden()
            ->assertSee('Access denied');
    }

    public function test_custom_not_found_page_renders(): void
    {
        $this->get('/definitely-not-a-route')
            ->assertNotFound()
            ->assertSee('Page not found');
    }

    public function test_instructions_download_is_access_controlled(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $outsider = User::factory()->create(['role' => 'student']);
        $subject = \App\Models\Subject::factory()->create(['created_by' => $teacher->id]);
        $exam = \App\Models\Exam::factory()->create([
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'status' => 'published',
            'available_from' => null,
            'available_to' => null,
            'instructions_file' => \Illuminate\Http\UploadedFile::fake()
                ->create('guide.pdf', 50, 'application/pdf')
                ->store('exam-instructions', 'public'),
        ]);

        $url = route('exams.instructions', $exam);

        // Guest first: actingAs persists for later requests in the test.
        $this->get($url)->assertRedirect('/login');

        $response = $this->actingAs($teacher)->get($url);
        $response->assertOk();
        $this->assertStringStartsWith(
            'inline;',
            $response->headers->get('Content-Disposition')
        );
        \App\Models\ExamSession::create([
            'exam_id' => $exam->id, 'student_id' => $student->id,
            'teacher_id' => $teacher->id, 'status' => 'in_progress',
            'started_at' => now(), 'total_questions' => 0,
        ]);
        $this->actingAs($student)->get($url)->assertOk();
        $this->actingAs($outsider)->get($url)->assertForbidden();
    }

    public function test_force_end_writes_audit_log(): void
    {
        \Illuminate\Support\Facades\Log::spy();
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $subject = \App\Models\Subject::factory()->create(['created_by' => $teacher->id]);
        $exam = \App\Models\Exam::factory()->create([
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'status' => 'published',
            'available_from' => null,
            'available_to' => null,
        ]);
        $session = \App\Models\ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'total_questions' => 0,
        ]);

        $this->actingAs($teacher)->post(route('teacher.monitor.force-end', $session))
            ->assertRedirect();

        \Illuminate\Support\Facades\Log::shouldHaveReceived('info')
            ->withArgs(fn ($message, $context) => str_contains($message, 'force') && ($context['session_id'] ?? null) === $session->id);
    }
}
