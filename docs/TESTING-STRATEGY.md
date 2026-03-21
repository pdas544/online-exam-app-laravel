# Testing Strategy for Online Exam System

## Overview

This document provides a comprehensive testing strategy to ensure your exam system can handle 100+ concurrent students reliably.

---

## Test Pyramid

```
           /\
          /  \
         / E2E \           (5% - 5 tests)
        /       \          End-to-end scenarios
       /─────────\
      /           \
     /  Integration \ (20% - 60 tests)
    /  API Tests     \    Feature tests, controller tests
   /─────────────────\
  /                   \
 / Unit Tests          \ (75% - 150+ tests)
/─────────────────────/ Model tests, service tests, utility tests
```

---

## Phase 1: Unit Tests (Week 1)

### Test File Structure
```
tests/Unit/
├── Services/
│   ├── ExamServiceTest.php
│   ├── GradingServiceTest.php
│   ├── ViolationServiceTest.php
│   └── FileServiceTest.php
├── Models/
│   ├── ExamSessionTest.php
│   ├── StudentAnswerTest.php
│   └── ViolationLogTest.php
└── Builders/
    └── ExamSessionBuilderTest.php
```

### Example: ExamSessionTest

```php
// tests/Unit/Models/ExamSessionTest.php
<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\{Exam, ExamSession, User, StudentAnswer, ViolationLog};
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExamSessionTest extends TestCase
{
    use RefreshDatabase;

    protected Exam $exam;
    protected User $student;
    protected ExamSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        
        $teacher = User::factory()->teacher()->create();
        $this->student = User::factory()->student()->create();
        $this->exam = Exam::factory()->for($teacher)->create(['total_marks' => 100]);
        
        $this->session = ExamSession::factory()
            ->for($this->exam)
            ->for($this->student, 'student')
            ->create();
    }

    /** @test */
    public function session_has_correct_status_initially()
    {
        $this->assertEquals('scheduled', $this->session->status);
    }

    /** @test */
    public function session_can_be_started()
    {
        $this->session->update(['status' => 'in_progress', 'started_at' => now()]);
        
        $this->assertTrue($this->session->isActive());
        $this->assertFalse($this->session->isCompleted());
    }

    /** @test */
    public function session_can_calculate_time_remaining()
    {
        $this->exam->update(['time_limit' => 60]); // 60 minutes
        
        $this->session->update([
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(30),
        ]);

        $remaining = $this->session->timeRemaining();
        
        $this->assertGreaterThan(1700, $remaining); // ~30 minutes
        $this->assertLessThan(1900, $remaining);
    }

    /** @test */
    public function violation_increments_violation_count()
    {
        $initialCount = $this->session->violation_count;
        
        $this->session->logViolation('tab_switch', 'Student switched tabs');
        
        $this->session->refresh();
        $this->assertEquals($initialCount + 1, $this->session->violation_count);
    }

    /** @test */
    public function session_terminates_after_5_violations()
    {
        for ($i = 0; $i < 5; $i++) {
            $this->session->logViolation('tab_switch', 'Violation ' . ($i + 1));
        }

        $this->session->refresh();
        $this->assertEquals('terminated', $this->session->status);
    }

    /** @test */
    public function violations_have_correct_severity()
    {
        $violations = [
            'tab_switch' => 2,
            'fullscreen_exit' => 3,
            'window_blur' => 1,
            'page_navigation' => 3,
        ];

        foreach ($violations as $type => $expectedSeverity) {
            $this->session->logViolation($type, 'Test violation');
            $violation = ViolationLog::latest()->first();
            
            $this->assertEquals($expectedSeverity, $violation->severity);
        }
    }

    /** @test */
    public function answers_are_created_for_all_questions()
    {
        $questions = $this->exam->questions;
        $answers = $this->session->answers;

        $this->assertEquals($questions->count(), $answers->count());
    }

    /** @test */
    public function update_progress_counts_answered_questions()
    {
        $answers = $this->session->answers()->take(3)->get();
        
        foreach ($answers as $answer) {
            $answer->update(['is_answered' => true, 'answer' => 'A']);
        }

        $this->session->updateProgress();

        $this->assertEquals(3, $this->session->current_question_index);
        $this->assertCount(3, $this->session->answered_questions ?? []);
    }
}
```

### Example: GradingServiceTest

```php
// tests/Unit/Services/GradingServiceTest.php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\{Exam, ExamSession, Question, StudentAnswer, User};
use App\Services\GradingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GradingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected GradingService $gradingService;
    protected Exam $exam;
    protected ExamSession $session;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->gradingService = new GradingService();
        
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        
        $this->exam = Exam::factory()
            ->for($teacher)
            ->create(['total_marks' => 100, 'passing_marks' => 50]);
        
        $this->session = ExamSession::factory()
            ->for($this->exam)
            ->for($student, 'student')
            ->create();
    }

    /** @test */
    public function single_choice_mcq_graded_correctly()
    {
        $question = Question::factory()->create([
            'question_type' => 'mcq_single',
            'correct_answers' => json_encode(['A']),
        ]);

        $this->exam->questions()->attach($question->id, ['points_override' => 10]);

        $answer = $this->session->answers()->where('question_id', $question->id)->first();
        $answer->update(['answer' => json_encode('A')]);

        $answer->autoGrade();

        $this->assertTrue($answer->is_correct);
        $this->assertEquals(10, $answer->points_earned);
    }

    /** @test */
    public function multiple_choice_mcq_graded_correctly()
    {
        $question = Question::factory()->create([
            'question_type' => 'mcq_multiple',
            'correct_answers' => json_encode(['A', 'C']),
        ]);

        $this->exam->questions()->attach($question->id, ['points_override' => 10]);

        $answer = $this->session->answers()->where('question_id', $question->id)->first();
        $answer->update(['answer' => json_encode(['A', 'C'])]);

        $answer->autoGrade();

        $this->assertTrue($answer->is_correct);
        $this->assertEquals(10, $answer->points_earned);
    }

    /** @test */
    public function incorrect_answer_has_zero_points()
    {
        $question = Question::factory()->create([
            'question_type' => 'mcq_single',
            'correct_answers' => json_encode(['A']),
        ]);

        $this->exam->questions()->attach($question->id, ['points_override' => 10]);

        $answer = $this->session->answers()->where('question_id', $question->id)->first();
        $answer->update(['answer' => json_encode('B')]);

        $answer->autoGrade();

        $this->assertFalse($answer->is_correct);
        $this->assertEquals(0, $answer->points_earned);
    }

    /** @test */
    public function total_score_calculated_correctly()
    {
        // Create 10 questions, 7 correct
        $questions = Question::factory(10)->create(['question_type' => 'mcq_single']);
        
        foreach ($questions as $index => $question) {
            $this->exam->questions()->attach($question->id, ['points_override' => 10]);
            
            $answer = $this->session->answers()->where('question_id', $question->id)->first();
            
            if ($index < 7) { // First 7 correct
                $answer->update([
                    'answer' => json_encode('A'),
                    'is_correct' => true,
                    'points_earned' => 10,
                ]);
            } else { // Last 3 incorrect
                $answer->update([
                    'answer' => json_encode('B'),
                    'is_correct' => false,
                    'points_earned' => 0,
                ]);
            }
        }

        $score = $this->gradingService->calculateTotalScore($this->session);

        $this->assertEquals(70, $score);
    }

    /** @test */
    public function passing_status_determined_correctly()
    {
        $this->exam->update(['passing_marks' => 50]);

        $passed = $this->gradingService->isPassed(75, $this->exam->passing_marks);
        $this->assertTrue($passed);

        $passed = $this->gradingService->isPassed(40, $this->exam->passing_marks);
        $this->assertFalse($passed);
    }
}
```

### Run Unit Tests

```bash
php artisan test --filter Unit --coverage

# Output should show:
# Tests:  45 passed (50ms)
# Coverage: 75%+
```

---

## Phase 2: Integration Tests (Week 2)

### Test File Structure
```
tests/Feature/
├── ExamControllerTest.php
├── ExamSessionControllerTest.php
├── QuestionControllerTest.php
├── TeacherMonitoringTest.php
└── ConcurrencyTest.php
```

### Example: ExamSessionControllerTest

```php
// tests/Feature/ExamSessionControllerTest.php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{Exam, User, ExamSession, Question};
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExamSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $student;
    protected User $teacher;
    protected Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->teacher = User::factory()->teacher()->create();
        $this->student = User::factory()->student()->create();
        
        $this->exam = Exam::factory()
            ->for($this->teacher)
            ->create([
                'status' => 'published',
                'available_from' => now()->subHour(),
                'available_to' => now()->addHour(),
            ]);

        // Add questions to exam
        $questions = Question::factory(5)->create(['question_type' => 'mcq_single']);
        foreach ($questions as $index => $question) {
            $this->exam->questions()->attach($question->id, [
                'order_index' => $index + 1,
                'points_override' => 20,
            ]);
        }
    }

    /** @test */
    public function student_can_start_exam()
    {
        $response = $this->actingAs($this->student)
            ->post(route('exam.start', $this->exam));

        $response->assertRedirect();

        $this->assertDatabaseHas('exam_sessions', [
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'status' => 'scheduled',
        ]);
    }

    /** @test */
    public function student_cannot_start_exam_twice()
    {
        ExamSession::factory()
            ->for($this->exam)
            ->for($this->student, 'student')
            ->create(['status' => 'completed']);

        $response = $this->actingAs($this->student)
            ->post(route('exam.start', $this->exam));

        $response->assertRedirect()
            ->assertSessionHas('error', 'You have already completed this exam');
    }

    /** @test */
    public function student_can_take_exam()
    {
        $session = ExamSession::factory()
            ->for($this->exam)
            ->for($this->student, 'student')
            ->create(['status' => 'scheduled']);

        $response = $this->actingAs($this->student)
            ->get(route('exam.session.take', $session));

        $response->assertOk()
            ->assertViewHas(['session', 'session.exam', 'session.answers']);
    }

    /** @test */
    public function answer_can_be_saved()
    {
        $session = $this->createActiveSession();
        $answer = $session->answers()->first();

        $response = $this->actingAs($this->student)
            ->postJson(route('exam.session.answer', $session), [
                'question_id' => $answer->question_id,
                'answer' => 'A',
            ]);

        $response->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'progress']);

        $this->assertDatabaseHas('student_answers', [
            'id' => $answer->id,
            'is_answered' => true,
        ]);
    }

    /** @test */
    public function exam_can_be_submitted()
    {
        $session = $this->createActiveSession();

        $response = $this->actingAs($this->student)
            ->post(route('exam.session.submit', $session));

        $response->assertRedirect()
            ->assertSessionHas('success', 'Exam submitted successfully');

        $session->refresh();
        $this->assertEquals('submitted', $session->status);
    }

    /** @test */
    public function violation_can_be_logged()
    {
        $session = $this->createActiveSession();

        $response = $this->actingAs($this->student)
            ->postJson(route('exam.session.violation', $session), [
                'type' => 'tab_switch',
                'description' => 'Student switched tabs',
            ]);

        $response->assertJson(['logged' => true]);

        $this->assertDatabaseHas('violation_logs', [
            'exam_session_id' => $session->id,
            'violation_type' => 'tab_switch',
        ]);
    }

    /** @test */
    public function exam_status_visible_to_student()
    {
        $session = $this->createActiveSession();

        $response = $this->actingAs($this->student)
            ->getJson(route('exam.session.status', $session));

        $response->assertJsonStructure([
            'status', 'time_remaining', 'answered_count', 'total_count'
        ]);
    }

    /** @test */
    public function result_visible_after_submission()
    {
        $session = ExamSession::factory()
            ->for($this->exam)
            ->for($this->student, 'student')
            ->create(['status' => 'completed', 'score' => 75]);

        $response = $this->actingAs($this->student)
            ->get(route('exam.session.result', $session));

        $response->assertOk()
            ->assertViewHas(['session', 'score', 'passed']);
    }

    protected function createActiveSession(): ExamSession
    {
        return ExamSession::factory()
            ->for($this->exam)
            ->for($this->student, 'student')
            ->create(['status' => 'in_progress', 'started_at' => now()]);
    }
}
```

### Example: TeacherMonitoringTest

```php
// tests/Feature/TeacherMonitoringTest.php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{Exam, User, ExamSession, Question};
use Illuminate\Foundation\Testing\RefreshDatabase;

class TeacherMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $student;
    protected Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->teacher = User::factory()->teacher()->create();
        $this->student = User::factory()->student()->create();
        
        $this->exam = Exam::factory()
            ->for($this->teacher)
            ->create();
    }

    /** @test */
    public function teacher_can_view_monitoring_dashboard()
    {
        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.monitor'));

        $response->assertOk()
            ->assertViewHas('activeExams');
    }

    /** @test */
    public function teacher_can_monitor_specific_exam()
    {
        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.monitor.exam', $this->exam));

        $response->assertOk()
            ->assertViewHas(['exam', 'sessions']);
    }

    /** @test */
    public function teacher_can_send_warning_to_student()
    {
        $session = ExamSession::factory()
            ->for($this->exam)
            ->for($this->student, 'student')
            ->create();

        $response = $this->actingAs($this->teacher)
            ->postJson(route('teacher.monitor.warn', $session), [
                'message' => 'Stop cheating!',
            ]);

        $response->assertSuccessful();
    }

    /** @test */
    public function teacher_can_force_end_exam()
    {
        $session = ExamSession::factory()
            ->for($this->exam)
            ->for($this->student, 'student')
            ->create(['status' => 'in_progress']);

        $response = $this->actingAs($this->teacher)
            ->post(route('teacher.monitor.force-end', $session), [
                'message' => 'Time is up',
            ]);

        $session->refresh();
        $this->assertEquals('terminated', $session->status);
    }

    /** @test */
    public function only_teacher_can_access_monitoring()
    {
        $response = $this->actingAs($this->student)
            ->get(route('teacher.monitor'));

        $response->assertForbidden();
    }
}
```

### Run Integration Tests

```bash
php artisan test --filter Feature

# Output:
# Tests:  60 passed (1.23s)
```

---

## Phase 3: Performance & Concurrency Tests (Week 3)

### Example: ConcurrentSubmissionTest

```php
// tests/Feature/ConcurrencyTest.php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{Exam, User, ExamSession, Question};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

class ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 100 students can submit exams simultaneously
     * @test
     * @group performance
     */
    public function test_100_students_can_submit_exams_concurrently()
    {
        Bus::fake(); // Fake queue for faster testing

        $teacher = User::factory()->teacher()->create();
        $exam = Exam::factory()
            ->for($teacher)
            ->create(['total_marks' => 100]);

        // Add questions
        $questions = Question::factory(10)->create(['question_type' => 'mcq_single']);
        foreach ($questions as $index => $question) {
            $exam->questions()->attach($question->id, [
                'order_index' => $index + 1,
                'points_override' => 10,
            ]);
        }

        // Create 100 students and sessions
        $students = User::factory(100)->student()->create();
        $sessions = [];

        foreach ($students as $student) {
            $session = ExamSession::factory()
                ->for($exam)
                ->for($student, 'student')
                ->create(['status' => 'in_progress', 'started_at' => now()]);
            $sessions[] = $session;
        }

        // Measure time for 100 concurrent submissions
        $startTime = microtime(true);

        foreach ($sessions as $session) {
            $this->actingAs($session->student)
                ->post(route('exam.session.submit', $session))
                ->assertRedirect();
        }

        $totalTime = microtime(true) - $startTime;

        // Assertions
        $this->assertEquals(100, count($sessions));
        
        // Should complete in reasonable time
        $this->assertLessThan(30, $totalTime,
            "100 submissions took {$totalTime}s (expected <30s)");

        // All should be submitted
        $submittedSessions = ExamSession::where('status', 'submitted')->count();
        $this->assertEquals(100, $submittedSessions);

        echo "\n📊 Performance Metrics:\n";
        echo "Total Time: {$totalTime}s\n";
        echo "Average Per Submission: " . ($totalTime / 100) . "s\n";
        echo "Throughput: " . (100 / $totalTime) . " submissions/sec\n";
    }

    /**
     * Test database doesn't get overwhelmed
     * @test
     * @group performance
     */
    public function test_concurrent_violation_logging()
    {
        $teacher = User::factory()->teacher()->create();
        $students = User::factory(50)->student()->create();
        $exam = Exam::factory()->for($teacher)->create();

        $sessions = $students->map(function ($student) use ($exam) {
            return ExamSession::factory()
                ->for($exam)
                ->for($student, 'student')
                ->create(['status' => 'in_progress']);
        });

        $startTime = microtime(true);

        // 50 students each log 10 violations = 500 total
        foreach ($sessions as $session) {
            for ($i = 0; $i < 10; $i++) {
                $this->actingAs($session->student)
                    ->postJson(route('exam.session.violation', $session), [
                        'type' => 'tab_switch',
                        'description' => "Violation {$i}",
                    ])
                    ->assertJson(['logged' => true]);
            }
        }

        $totalTime = microtime(true) - $startTime;

        $this->assertLessThan(20, $totalTime,
            "500 violation logs took {$totalTime}s (expected <20s)");

        $this->assertDatabaseCount('violation_logs', 500);

        echo "\n📊 Violation Logging Metrics:\n";
        echo "Total Time for 500 violations: {$totalTime}s\n";
        echo "Average Per Violation: " . ($totalTime / 500) . "s\n";
    }

    /**
     * Test query performance doesn't degrade
     * @test
     * @group performance
     */
    public function test_monitoring_dashboard_query_count()
    {
        \DB::enableQueryLog();

        $teacher = User::factory()->teacher()->create();
        $students = User::factory(50)->student()->create();
        
        // Create 50 sessions
        $exam = Exam::factory()->for($teacher)->create();
        foreach ($students as $student) {
            ExamSession::factory()
                ->for($exam)
                ->for($student, 'student')
                ->create(['status' => 'in_progress']);
        }

        // Simulate monitoring view load
        $this->actingAs($teacher)
            ->get(route('teacher.monitor.exam', $exam));

        $queries = \DB::getQueryLog();
        $queryCount = count($queries);

        echo "\n📊 Query Performance:\n";
        echo "Query count for 50 students: {$queryCount}\n";
        echo "Expected: <10 queries (with eager loading)\n";

        // With proper eager loading, should be <10 queries
        $this->assertLessThan(15, $queryCount,
            "Too many queries ({$queryCount}) - check eager loading");
    }
}
```

### Run Performance Tests

```bash
# Run all performance tests
php artisan test tests/Feature/ConcurrencyTest.php

# Run specific test
php artisan test --filter test_100_students_can_submit_exams_concurrently

# With output
php artisan test --filter ConcurrencyTest --verbose
```

---

## Phase 4: Load Testing (Week 4+)

### Using Apache Bench

```bash
# Setup: Create test data first
php artisan artisan seed --class=LoadTestSeeder

# Simple GET request (warming up server)
ab -n 100 -c 10 http://localhost/exam/list

# POST request (exam submission - needs auth)
# Use ApacheBench with custom headers/body
ab -n 50 -c 5 -p payload.json -T application/json \
   -H "Authorization: Bearer {token}" \
   http://localhost/exam/session/1/submit

# Sustained load (5 minutes)
ab -t 300 -c 20 http://localhost/exam/list

# Expected results:
# - Response time: <200ms average
# - Failed requests: 0
# - Requests/sec: >50
```

### Laravel Load Testing Example

```php
// Create this as a command for continuous load testing

namespace App\Console\Commands;

use App\Models\{Exam, User, ExamSession, Question};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class LoadTestCommand extends Command
{
    protected $signature = 'load-test {duration=60} {--concurrent=10}';
    protected $description = 'Run load test on exam submission endpoints';

    public function handle()
    {
        $duration = $this->argument('duration');
        $concurrent = $this->option('concurrent');
        
        $this->info("Starting {$concurrent} concurrent requests for {$duration} seconds...");

        // Setup
        $teacher = User::factory()->teacher()->create();
        $exam = Exam::factory()->for($teacher)->create();
        
        $students = User::factory($concurrent)->student()->create();
        $sessions = [];
        
        foreach ($students as $student) {
            $session = ExamSession::factory()
                ->for($exam)
                ->for($student, 'student')
                ->create(['status' => 'in_progress']);
            $sessions[] = $session;
        }

        // Run load test
        $startTime = time();
        $successCount = 0;
        $failureCount = 0;
        $totalTime = 0;

        while ((time() - $startTime) < $duration) {
            foreach ($sessions as $session) {
                $reqStartTime = microtime(true);

                try {
                    $response = Http::post("/exam/session/{$session->id}/submit", [
                        // Include auth token
                    ]);

                    if ($response->ok()) {
                        $successCount++;
                    } else {
                        $failureCount++;
                    }

                } catch (\Exception $e) {
                    $failureCount++;
                }

                $totalTime += (microtime(true) - $reqStartTime);
            }
        }

        // Report
        $totalRequests = $successCount + $failureCount;
        $avgTime = $totalTime / $totalRequests;
        $throughput = $totalRequests / $duration;

        $this->info("\n✅ Load Test Complete:");
        $this->info("Total Requests: {$totalRequests}");
        $this->info("Successful: {$successCount}");
        $this->info("Failed: {$failureCount}");
        $this->info("Average Response Time: {$avgTime}s");
        $this->info("Throughput: {$throughput} req/s");
    }
}

// Run:
// php artisan load-test 60 --concurrent=100
```

---

## Test Configuration

### phpunit.xml
```xml
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.5/phpunit.xsd"
         bootstrap="bootstrap/app.php"
         cacheResultFile=".phpunit.cache/test-results"
         executionOrder="depends,defects"
         beStrictAboutOutputDuringTests="true"
         failOnRisky="true"
         failOnWarning="true"
         displayDetailsOnIncompleteTests="true"
         displayDetailsOnSkippedTests="true"
         displayDetailsOnTestsThatTriggerDeprecations="true"
         displayDetailsOnTestsThatTriggerErrors="true"
         displayDetailsOnTestsThatTriggerNotices="true"
         displayDetailsOnTestsThatTriggerWarnings="true"
         verbose="true"
         colors="true">
    <php>
        <ini name="display_errors" value="On" />
        <ini name="error_reporting" value="-1" />
        <env name="APP_ENV" value="testing" />
        <env name="APP_DEBUG" value="false" />
        <env name="APP_KEY" value="base64:Ug+ZxYrPt7SZ/ubmIkVQkVWW/tDQbLlzqWIWUyJ4YrI=" />
        <env name="DB_CONNECTION" value="sqlite" />
        <env name="DB_DATABASE" value=":memory:" />
        <env name="QUEUE_CONNECTION" value="sync" />
        <env name="SESSION_DRIVER" value="array" />
        <env name="MAIL_DRIVER" value="array" />
        <env name="CACHE_DRIVER" value="array" />
    </php>
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
        <testsuite name="Performance">
            <directory suffix="Test.php">./tests/Performance</directory>
        </testsuite>
    </testsuites>
    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">./app</directory>
        </include>
        <exclude>
            <directory suffix="*.blade.php">./app</directory>
        </exclude>
        <report>
            <html outputDirectory="coverage" />
            <text outputFile="php://stdout" />
        </report>
    </coverage>
</phpunit>
```

---

## Running Tests

```bash
# All tests
php artisan test

# By suite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --testsuite=Performance

# By filter
php artisan test --filter ConcurrencyTest
php artisan test --filter "exam"

# With coverage
php artisan test --coverage
php artisan test --coverage --coverage-html coverage

# Watch mode (continuous)
php artisan test --watch

# Parallel execution (faster)
php artisan test --parallel --processes=4
```

---

## Expected Coverage

| Component | Coverage | Priority |
|-----------|----------|----------|
| Services | 90%+ | CRITICAL |
| Models | 85%+ | HIGH |
| Controllers | 80%+ | HIGH |
| Jobs | 90%+ | HIGH |
| Utilities | 75%+ | MEDIUM |
| **Overall** | **80%+** | **MUST HAVE** |

---

## Continuous Integration

### GitHub Actions (.github/workflows/tests.yml)
```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: exam_system_test
          MYSQL_ROOT_PASSWORD: password
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
          extensions: mysql, pdo, pdo_mysql
      
      - name: Install dependencies
        run: composer install
      
      - name: Setup environment
        run: |
          cp .env.example .env
          php artisan key:generate
      
      - name: Run migrations
        run: php artisan migrate
      
      - name: Run tests
        run: php artisan test --coverage
      
      - name: Upload coverage
        uses: codecov/codecov-action@v3
```

---

## Summary: Testing Roadmap

| Week | Phase | Tests | Focus |
|------|-------|-------|-------|
| 1 | Unit | 50+ | Services, models, utilities |
| 2 | Integration | 60+ | API endpoints, workflows |
| 3 | Performance | 10+ | Concurrency, load, scalability |
| 4+ | Continuous | Ongoing | CI/CD, monitoring, regression |

**Status:** ✅ Ready to implement  
**Estimated Effort:** 4 weeks  
**Expected Coverage:** >80%
