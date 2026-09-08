<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_health_metrics(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);
        $subject = Subject::factory()->create(['created_by' => $teacher->id]);
        $exam = Exam::factory()->create([
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'status' => 'published',
            'available_from' => null,
            'available_to' => null,
        ]);
        ExamSession::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'teacher_id' => $teacher->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'total_questions' => 0,
        ]);

        $response = $this->actingAs($admin)->getJson(route('admin.metrics'));

        $response->assertOk()->assertJsonStructure([
            'sessions_by_status',
            'queue_depth',
            'failed_jobs',
            'violations_last_hour',
            'live_exams',
        ]);
        $this->assertEquals(1, $response->json('sessions_by_status.in_progress'));
        $this->assertEquals(1, $response->json('live_exams'));
    }

    public function test_metrics_forbidden_for_non_admins(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($teacher)->getJson(route('admin.metrics'))->assertForbidden();
        $this->actingAs($student)->getJson(route('admin.metrics'))->assertForbidden();
        $this->getJson(route('admin.metrics'))->assertForbidden();
    }
}
