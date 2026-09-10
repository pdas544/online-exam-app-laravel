<?php

namespace Tests\Feature;

use App\Jobs\GradeExamSession;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\ExamSessionService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Guards the 100-simultaneous-submit path: exactly one active session per
 * (exam, student) and exactly one grading job per submit burst.
 */
class ConcurrentStartTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $student;

    private Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        // Lock waits are tuned down so contention tests fail fast instead of
        // sleeping the production 5s block window.
        config(['exam.start_lock_wait' => 1]);

        $this->teacher = User::factory()->create(['role' => 'teacher']);
        $this->student = User::factory()->create(['role' => 'student']);
        $subject = Subject::factory()->create(['created_by' => $this->teacher->id]);
        $this->exam = Exam::factory()->create([
            'subject_id' => $subject->id,
            'teacher_id' => $this->teacher->id,
            'status' => 'published',
            'available_from' => null,
            'available_to' => null,
            'passing_marks' => 40,
            'max_attempts' => 3,
            'time_limit' => 60,
        ]);
        Question::factory()->count(2)->create([
            'subject_id' => $subject->id,
            'created_by' => $this->teacher->id,
        ]);
        $this->exam->questions()->attach(
            Question::where('subject_id', $subject->id)->pluck('id')->mapWithKeys(
                fn ($id, $i) => [$id => ['order_index' => $i + 1]]
            )->toArray()
        );
    }

    private function service(): ExamSessionService
    {
        return new ExamSessionService;
    }

    public function test_repeated_starts_yield_single_session(): void
    {
        $ids = [];
        for ($i = 0; $i < 10; $i++) {
            $ids[] = $this->service()->start($this->exam, $this->student->id)->id;
        }

        $this->assertCount(1, array_unique($ids));
        $this->assertEquals(1, ExamSession::where('exam_id', $this->exam->id)
            ->where('student_id', $this->student->id)
            ->count());
    }

    public function test_start_blocks_while_start_lock_held(): void
    {
        Cache::lock(
            ExamSessionService::startLockKey($this->exam->id, $this->student->id),
            10
        )->acquire();

        $this->expectException(LockTimeoutException::class);
        $this->service()->start($this->exam, $this->student->id);
    }

    public function test_double_submit_dispatches_grading_once(): void
    {
        Queue::fake();

        $session = $this->service()->start($this->exam, $this->student->id);
        $session->update(['status' => 'in_progress', 'started_at' => now()]);

        $this->service()->submit($session->fresh());
        $this->service()->submit($session->fresh());

        Queue::assertPushed(GradeExamSession::class, 1);
    }

    public function test_submit_redispatches_when_grading_dispatch_is_stale(): void
    {
        Queue::fake();

        $session = $this->service()->start($this->exam, $this->student->id);
        $session->update(['status' => 'in_progress', 'started_at' => now()]);

        $this->service()->submit($session->fresh());

        // Worker never picked the job up (e.g. worker down): an old dispatch
        // timestamp must allow recovery via re-dispatch.
        $session->fresh()->update(['grading_dispatched_at' => now()->subMinutes(20)]);
        $this->service()->submit($session->fresh());

        Queue::assertPushed(GradeExamSession::class, 2);
    }

    public function test_submit_blocks_while_submit_lock_held(): void
    {
        $session = $this->service()->start($this->exam, $this->student->id);
        $session->update(['status' => 'in_progress', 'started_at' => now()]);

        Cache::lock(ExamSessionService::submitLockKey($session->id), 10)->acquire();

        $this->expectException(LockTimeoutException::class);
        $this->service()->submit($session->fresh());
    }
}
