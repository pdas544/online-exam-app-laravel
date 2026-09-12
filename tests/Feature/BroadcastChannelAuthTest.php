<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BroadcastChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private User $stranger;

    private User $admin;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        // Channel callbacks register on the boot-time default broadcaster.
        // Re-resolve the manager on pusher BEFORE anything touches it so
        // auth runs the real callbacks (socket_auth is local HMAC).
        config()->set('broadcasting.default', 'pusher');
        config()->set('broadcasting.connections.pusher', [
            'driver' => 'pusher',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => 'test-app',
            'options' => ['cluster' => 'mt1', 'useTLS' => false],
        ]);
        app()->forgetInstance('Illuminate\Broadcasting\BroadcastManager');
        app()->booted(fn () => require base_path('routes/channels.php'));

        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->student = User::factory()->create(['role' => 'student']);
        $this->stranger = User::factory()->create(['role' => 'student']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $subject = Subject::factory()->create(['created_by' => $this->teacher->id]);
        $this->exam = Exam::factory()->create([
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'status' => 'published',
            'available_from' => null,
            'available_to' => null,
        ]);
        ExamSession::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'teacher_id' => $this->teacher->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'total_questions' => 0,
        ]);
    }

    private function auth(string $channel, User $user)
    {
        return $this->actingAs($user)->postJson('/broadcasting/auth', [
            'channel_name' => $channel,
            'socket_id' => '123.456',
        ]);
    }

    public function test_enrolled_student_may_listen_exam_channel(): void
    {
        $this->auth("private-exam.{$this->exam->id}", $this->student)->assertOk();
    }

    public function test_stranger_may_not_listen_exam_channel(): void
    {
        $this->auth("private-exam.{$this->exam->id}", $this->stranger)->assertForbidden();
    }

    public function test_owner_teacher_and_admin_may_listen_exam_channel(): void
    {
        $this->auth("private-exam.{$this->exam->id}", $this->teacher)->assertOk();
        $this->auth("private-exam.{$this->exam->id}", $this->admin)->assertOk();
    }

    public function test_student_channel_is_self_or_admin_only(): void
    {
        $this->auth("private-student.{$this->student->id}", $this->student)->assertOk();
        $this->auth("private-student.{$this->student->id}", $this->admin)->assertOk();
        $this->auth("private-student.{$this->student->id}", $this->stranger)->assertForbidden();
    }

    public function test_teacher_channel_is_self_or_admin_only(): void
    {
        $this->auth("private-teacher.{$this->teacher->id}", $this->teacher)->assertOk();
        $this->auth("private-teacher.{$this->teacher->id}", $this->student)->assertForbidden();
    }

    public function test_guest_cannot_authenticate_any_channel(): void
    {
        // /broadcasting/auth carries only the web group: the broadcaster
        // itself must reject unauthenticated subscribers.
        $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-exam.{$this->exam->id}",
            'socket_id' => '123.456',
        ])->assertForbidden();
    }
}
