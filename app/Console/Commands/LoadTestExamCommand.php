<?php

namespace App\Console\Commands;

use App\LoadTesting\RunResults;
use App\LoadTesting\Scenario;
use App\LoadTesting\StudentBot;
use App\LoadTesting\TeacherBot;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

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
            $this->line('bot 1 actions: '.json_encode(Scenario::forBot(1, $seed)));

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
        for ($i = 1; $i <= $count; $i++) {
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
        $this->line("results: {$dir}");
        $this->line('Note: a queue worker must be running for ExamSession grading jobs.');

        return self::SUCCESS;
    }
}
