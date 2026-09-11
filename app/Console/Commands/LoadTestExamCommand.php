<?php

namespace App\Console\Commands;

use App\LoadTesting\RunResults;
use App\LoadTesting\Scenario;
use App\LoadTesting\StudentBot;
use App\LoadTesting\TeacherBot;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Question;
use App\Models\StudentAnswer;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

class LoadTestExamCommand extends Command
{
    protected $signature = 'exams:load-test {students=100} {--seed=} {--base-url=http://localhost:8080} {--dry-run}';

    protected $description = 'Build a fixture exam and fork one bot per student plus a teacher bot to load-test it. Must be invoked with the load-test env (the runner sets the load DB via --env or .env.loadtest); uses the current database connection as-is.';

    public function handle(): int
    {
        $seed = $this->option('seed');
        if (! is_string($seed) || $seed === '') {
            $seed = (string) time();
        }
        $this->info("seed: $seed");

        $count = (int) $this->argument('students');
        $baseUrl = (string) $this->option('base-url');

        if ($this->option('dry-run')) {
            $this->line("dry-run: would create 1 teacher, 1 exam (10 questions, 5 points each), {$count} students");
            $this->line('bot 0 actions: '.json_encode(Scenario::forBot(0, $seed)));

            return self::SUCCESS;
        }

        if (! function_exists('pcntl_fork')) {
            $this->error('pcntl extension is required for exams:load-test.');

            return self::FAILURE;
        }

        $this->call('migrate:fresh', ['--force' => true]);

        $passwordHash = Hash::make('loadtest123');

        $teacherEmail = 'loadtest-teacher@example.com';
        User::factory()->create([
            'role' => 'teacher',
            'email' => $teacherEmail,
            'password' => $passwordHash,
        ]);
        $teacher = User::where('email', $teacherEmail)->firstOrFail();

        Subject::factory()->create(['created_by' => $teacher->id]);
        $subject = Subject::where('created_by', $teacher->id)->firstOrFail();

        Exam::factory()->create([
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'status' => 'published',
            'time_limit' => 120,
            'max_attempts' => 5,
            'available_from' => null,
            'available_to' => null,
        ]);
        $exam = Exam::where('teacher_id', $teacher->id)->firstOrFail();

        $questionIds = [];
        for ($i = 1; $i <= 10; $i++) {
            $qid = (int) Question::factory()->create([
                'subject_id' => $subject->id,
                'created_by' => $teacher->id,
                'question_type' => 'mcq_single',
                'options' => ['A' => 'First', 'B' => 'Second'],
                'correct_answers' => ['B'],
                'points' => 5,
            ])->getKey();
            $exam->questions()->attach($qid, ['order_index' => $i]);
            $questionIds[] = $qid;
        }
        $exam->updateTotalMarks();

        $students = [];
        for ($i = 0; $i < $count; $i++) {
            $email = "loadtest-student-{$i}@example.com";
            User::factory()->create([
                'role' => 'student',
                'email' => $email,
                'password' => $passwordHash,
            ]);
            $students[] = ['id' => $i, 'email' => $email];
        }

        $this->line("fixture: 1 teacher, 1 exam ({$exam->id}, 10 questions), {$count} students");

        $safeSeed = preg_replace('/[^A-Za-z0-9._-]/', '_', $seed) ?? 'seed';
        $dir = storage_path('app/loadtest/'.date('Ymd-His').'-'.$safeSeed);
        mkdir($dir, 0777, true);

        $examId = $exam->id;
        /** @var array<int, string> $children */
        $children = [];

        foreach ($students as $student) {
            $path = $dir.'/bot-'.$student['id'].'.jsonl';
            $botId = $student['id'];
            $email = $student['email'];
            $pid = pcntl_fork();
            if ($pid === -1) {
                $this->error("failed to fork student bot {$botId}");

                continue;
            }
            if ($pid === 0) {
                $bot = new StudentBot($botId, $email, 'loadtest123', $examId, $questionIds);
                $bot->run(Scenario::forBot($botId, $seed), $baseUrl, $path);
                exit(0);
            }
            $children[$pid] = "student {$botId}";
        }

        $teacherPath = $dir.'/teacher.jsonl';
        $teacherPid = pcntl_fork();
        if ($teacherPid === -1) {
            $this->error('failed to fork teacher bot');
        } elseif ($teacherPid === 0) {
            $bot = new TeacherBot($teacherEmail, 'loadtest123', $examId);
            $bot->run($baseUrl, $teacherPath);
            exit(0);
        } else {
            $children[$teacherPid] = 'teacher';
        }

        /** @var array<int, string> $failures */
        $failures = [];
        $deadline = time() + 600;
        while (count($children) > 0) {
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
            if ($pid > 0) {
                if (! pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
                    $failures[] = ($children[$pid] ?? "pid {$pid}").' exited abnormally';
                }
                unset($children[$pid]);

                continue;
            }
            if (time() >= $deadline) {
                foreach ($children as $straggler => $label) {
                    @posix_kill($straggler, SIGKILL);
                    $failures[] = "{$label} killed after 600s timeout";
                }
                foreach (array_keys($children) as $straggler) {
                    pcntl_waitpid($straggler, $status);
                }
                $children = [];

                break;
            }
            sleep(1);
        }

        $summary = RunResults::aggregate($dir);
        $rows = [];
        foreach ($summary['by_action'] as $action => $stats) {
            $rows[] = [$action, $stats['n'], $stats['p50'], $stats['p95'], json_encode($stats['codes'])];
        }
        $this->table(['action', 'n', 'p50 ms', 'p95 ms', 'codes'], $rows);
        $this->line("http_5xx: {$summary['http_5xx']} http_429: {$summary['http_429']} skipped: {$summary['skipped']}");
        foreach ($failures as $failure) {
            $this->warn($failure);
        }

        $lines = $this->readResultLines($dir);
        // Drain first: the grading spot-check needs finished grading jobs.
        $queuesDrained = $this->assertQueuesDrained();
        $assertions = [
            $this->assertNoServerErrors($summary),
            $this->assertNoRateLimitHits($summary),
            $this->assertSubmitIdempotent($lines),
            $this->assertNoDuplicateActiveSessions($examId),
            $this->assertEagerBeginRejected($lines),
            $this->assertSpamTerminates($examId),
            $this->assertGradingSpotCheck($examId),
            $queuesDrained,
            $this->assertChaosMinimums($lines, $count),
        ];
        foreach ($assertions as $assertion) {
            $label = $assertion['pass'] ? 'PASS' : 'FAIL';
            $this->line("{$label} {$assertion['name']} — {$assertion['detail']}");
        }
        $this->printLatencyReport($summary);

        $this->line("results: {$dir}");
        $this->line('Note: a queue worker must be running for ExamSession grading jobs.');

        foreach ($assertions as $assertion) {
            if (! $assertion['pass']) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{bot: int|string, action: string, status: int, body: string}>
     */
    private function readResultLines(string $dir): array
    {
        $lines = [];
        foreach (glob(rtrim($dir, '/').'/*.jsonl') ?: [] as $file) {
            $raw = @file($file, FILE_IGNORE_NEW_LINES);
            if (! is_array($raw)) {
                continue;
            }
            foreach ($raw as $row) {
                $decoded = json_decode($row, true);
                if (! is_array($decoded)
                    || ! isset($decoded['action']) || ! is_string($decoded['action'])
                    || ! isset($decoded['status']) || ! is_numeric($decoded['status'])
                ) {
                    continue;
                }
                $bot = $decoded['bot'] ?? '?';
                $lines[] = [
                    'bot' => is_int($bot) || is_string($bot) ? $bot : '?',
                    'action' => $decoded['action'],
                    'status' => (int) $decoded['status'],
                    'body' => isset($decoded['body']) && is_string($decoded['body']) ? $decoded['body'] : '',
                ];
            }
        }

        return $lines;
    }

    /**
     * @param  array{by_action: array<string, array{n: int, p50: float, p95: float, codes: array<int, int>}>, http_5xx: int, http_429: int, skipped: int}  $summary
     * @return array{name: string, pass: bool, detail: string}
     */
    private function assertNoServerErrors(array $summary): array
    {
        return [
            'name' => 'no_server_errors',
            'pass' => $summary['http_5xx'] === 0,
            'detail' => "http_5xx={$summary['http_5xx']}",
        ];
    }

    /**
     * @param  array{by_action: array<string, array{n: int, p50: float, p95: float, codes: array<int, int>}>, http_5xx: int, http_429: int, skipped: int}  $summary
     * @return array{name: string, pass: bool, detail: string}
     */
    private function assertNoRateLimitHits(array $summary): array
    {
        return [
            'name' => 'no_rate_limit_hits',
            'pass' => $summary['http_429'] === 0,
            'detail' => "http_429={$summary['http_429']}",
        ];
    }

    /**
     * @param  array<int, array{bot: int|string, action: string, status: int, body: string}>  $lines
     * @return array{name: string, pass: bool, detail: string}
     */
    private function assertSubmitIdempotent(array $lines): array
    {
        /** @var array<string, array<int, array{bot: int|string, action: string, status: int, body: string}>> $byBot */
        $byBot = [];
        foreach ($lines as $line) {
            if (! in_array($line['action'], ['submit', 'submit_dup'], true)) {
                continue;
            }
            if ($line['bot'] === 'teacher') {
                continue;
            }
            $byBot[(string) $line['bot']][] = $line;
        }

        $checked = 0;
        $bad = [];
        foreach ($byBot as $bot => $submits) {
            if (count($submits) < 2) {
                continue;
            }
            $checked++;
            // Idempotency = same request twice yields the same response:
            // 200+200 with identical bodies, or identical rejections
            // (e.g. terminated sessions 400 twice with the same body).
            foreach ($submits as $submit) {
                if ($submit['status'] !== $submits[0]['status'] || $submit['body'] !== $submits[0]['body']) {
                    $bad[] = $bot;

                    break;
                }
            }
        }

        return [
            'name' => 'submit_idempotent',
            'pass' => count($bad) === 0,
            'detail' => $checked === 0
                ? 'no bot with ≥2 submit lines'
                : "checked {$checked} bots; mismatched=".json_encode($bad),
        ];
    }

    /**
     * @return array{name: string, pass: bool, detail: string}
     */
    private function assertNoDuplicateActiveSessions(int $examId): array
    {
        $dupes = ExamSession::where('exam_id', $examId)
            ->whereIn('status', ['scheduled', 'in_progress', 'paused'])
            ->groupBy('student_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('student_id');

        return [
            'name' => 'no_duplicate_active_sessions',
            'pass' => $dupes->isEmpty(),
            'detail' => $dupes->isEmpty()
                ? 'no student holds >1 active session'
                : 'students with >1 active session: '.$dupes->implode(','),
        ];
    }

    /**
     * @param  array<int, array{bot: int|string, action: string, status: int, body: string}>  $lines
     * @return array{name: string, pass: bool, detail: string}
     */
    private function assertEagerBeginRejected(array $lines): array
    {
        $rejected = 0;
        foreach ($lines as $line) {
            if ($line['action'] === 'eager_begin' && $line['status'] !== 200) {
                $rejected++;
            }
        }

        return [
            'name' => 'eager_begin_rejected',
            'pass' => $rejected >= 5,
            'detail' => "rejected={$rejected} (need ≥5)",
        ];
    }

    /**
     * @return array{name: string, pass: bool, detail: string}
     */
    private function assertSpamTerminates(int $examId): array
    {
        $student = User::where('email', 'loadtest-student-0@example.com')->first();
        if ($student === null) {
            return [
                'name' => 'spam_terminates',
                'pass' => false,
                'detail' => 'bot 0 student row missing (bots are spawned 1..N; see task-6 report)',
            ];
        }
        $session = ExamSession::where('exam_id', $examId)
            ->where('student_id', $student->getKey())
            ->orderByDesc('id')
            ->first();
        if ($session === null) {
            return [
                'name' => 'spam_terminates',
                'pass' => false,
                'detail' => "bot 0 has no session row for exam {$examId}",
            ];
        }

        return [
            'name' => 'spam_terminates',
            'pass' => $session->status === 'terminated',
            'detail' => "bot 0 session #{$session->getKey()} status={$session->status}",
        ];
    }

    /**
     * @return array{name: string, pass: bool, detail: string}
     */
    private function assertGradingSpotCheck(int $examId): array
    {
        $sessions = ExamSession::where('exam_id', $examId)
            ->where('status', 'completed')
            ->orderBy('id')
            ->limit(5)
            ->get();
        if ($sessions->isEmpty()) {
            return [
                'name' => 'grading_spot_check',
                'pass' => false,
                'detail' => 'no completed sessions to verify (submit path produced zero completions)',
            ];
        }

        $bad = [];
        foreach ($sessions as $session) {
            // Fresh read, never persisted: the fixture is uniform
            // (mcq_single, correct ['B'], 5 pts), so expected grading is
            // computed directly from each stored answer.
            $answers = StudentAnswer::where('exam_session_id', $session->getKey())->get();
            $earned = 0.0;
            $possible = 0.0;
            $answerBad = 0;
            foreach ($answers as $answer) {
                $stored = $answer->answer ?? [];
                $expectedCorrect = $answer->is_answered
                    && array_values($stored) === ['B'];
                $expectedPoints = $expectedCorrect ? 5.0 : 0.0;
                $possible += (float) $answer->max_points;
                $earned += $expectedPoints;
                if ((bool) $answer->is_correct !== $expectedCorrect
                    || round((float) $answer->points_earned, 2) !== round($expectedPoints, 2)
                ) {
                    $answerBad++;
                }
            }
            $percentage = $possible > 0 ? round($earned / $possible * 100, 2) : 0.0;
            if ($session->score === null || $answerBad > 0 || round((float) $session->score, 2) !== $percentage) {
                $bad[] = (string) $session->getKey();
            }
        }

        $checked = $sessions->count();

        return [
            'name' => 'grading_spot_check',
            'pass' => count($bad) === 0,
            'detail' => count($bad) === 0
                ? "{$checked}/{$checked} completed sessions graded correctly (2-decimal match)"
                : 'mismatched sessions: '.implode(',', $bad),
        ];
    }

    /**
     * @return array{name: string, pass: bool, detail: string}
     */
    private function assertQueuesDrained(): array
    {
        // Queue::size() honors the configured driver (database AND redis);
        // polling the jobs table directly is vacuous on non-database drivers.
        // Size every app queue: the default-only size() passes vacuously while
        // grading/violations/broadcasts pile up unworked.
        $drainSize = function (): int {
            $total = 0;
            foreach (['grading', 'violations', 'broadcasts'] as $queue) {
                try {
                    $total += Queue::connection()->size($queue);
                } catch (\Throwable) {
                    // Unresolvable queue on this driver counts as un-drained.
                    return PHP_INT_MAX;
                }
            }

            return $total + Queue::size();
        };
        try {
            for ($waited = 0; $waited < 60; $waited++) {
                $jobs = $drainSize();
                $failed = DB::table('failed_jobs')->count();
                if ($jobs === 0 && $failed === 0) {
                    return [
                        'name' => 'queues_drained',
                        'pass' => true,
                        'detail' => "queues empty after ~{$waited}s",
                    ];
                }
                sleep(1);
            }
            $jobs = $drainSize();
            $failed = DB::table('failed_jobs')->count();

            return [
                'name' => 'queues_drained',
                'pass' => false,
                'detail' => "jobs={$jobs} failed_jobs={$failed} after 60s",
            ];
        } catch (\Throwable $e) {
            return [
                'name' => 'queues_drained',
                'pass' => false,
                'detail' => 'queue poll failed: '.substr($e->getMessage(), 0, 160),
            ];
        }
    }

    /**
     * @param  array<int, array{bot: int|string, action: string, status: int, body: string}>  $lines
     * @return array{name: string, pass: bool, detail: string}
     */
    private function assertChaosMinimums(array $lines, int $students): array
    {
        $counts = ['pause' => 0, 'submit_dup' => 0, 'warn' => 0, 'end' => 0];
        foreach ($lines as $line) {
            if (array_key_exists($line['action'], $counts)) {
                $counts[$line['action']]++;
            }
        }
        $pass = $counts['pause'] >= 3
            && $counts['submit_dup'] >= $students
            && $counts['warn'] >= 2
            && $counts['end'] >= 1;

        return [
            'name' => 'chaos_minimums',
            'pass' => $pass,
            'detail' => "pause={$counts['pause']} (≥3) submit_dup={$counts['submit_dup']} (≥{$students}) warn={$counts['warn']} (≥2) end={$counts['end']} (≥1)",
        ];
    }

    /**
     * @param  array{by_action: array<string, array{n: int, p50: float, p95: float, codes: array<int, int>}>, http_5xx: int, http_429: int, skipped: int}  $summary
     */
    private function printLatencyReport(array $summary): void
    {
        foreach ($summary['by_action'] as $action => $stats) {
            $this->line("latency {$action}: n={$stats['n']} p50={$stats['p50']}ms p95={$stats['p95']}ms");
        }
        $budgets = [
            ['label' => 'status p95 <500ms', 'action' => 'status', 'budget' => 500.0],
            ['label' => 'answer p95 <1s', 'action' => 'answer', 'budget' => 1000.0],
            ['label' => 'submit-ack p95 <2s', 'action' => 'submit', 'budget' => 2000.0],
        ];
        foreach ($budgets as $budget) {
            $stats = $summary['by_action'][$budget['action']] ?? null;
            if ($stats === null) {
                $this->line("WARN {$budget['label']} — no data");

                continue;
            }
            $state = $stats['p95'] < $budget['budget'] ? 'WITHIN' : 'WARN';
            $this->line("{$state} {$budget['label']} (p95={$stats['p95']}ms)");
        }
    }
}
