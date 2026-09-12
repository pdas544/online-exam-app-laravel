# 100-Student Concurrent Load Test Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `subagent-driven-development` (recommended) or `executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A repeatable `exams:load-test` harness that runs 100 real student lifecycles (lobby → answer → violate → pause/resume → submit) concurrently against php-fpm + nginx + pgsql + redis, with seeded randomness and correctness assertions.

**Architecture:** Forked PHP bot processes (pcntl is installed) drive real HTTP through Guzzle cookie jars — no mocks, no sqlite. A seeded `Scenario` builder makes every run reproducible; scripted "chaos bots" guarantee edge transitions regardless of dice; a parent process aggregates per-action timings and asserts.

**Tech Stack:** php-fpm + nginx (new), pgsql 17 (running), redis-server (binary present) for queue, Guzzle 7.10 (in vendor), pcntl.

**Spec:** Prior decisions — forked processes, redis queue, 100 default + 10 smoke, manual runs only (no CI).

## Global Constraints

- `composer run test` (sqlite `:memory:`, sync queue) is untouched — the harness never runs under phpunit.
- Never `git add -A`; stage explicitly; commit per task.
- `./vendor/bin/pint --test` on touched PHP files; `./vendor/bin/phpstan analyse` level 5 clean.
- Raw SQL/operators must be pgsql+sqlite compatible (`like`, never `ilike`).
- Test-first: unit-testable pieces (`Scenario`, `Results`, rate-limit wiring) get PHPUnit tests; process orchestration is verified by `--dry-run` + the 10-student smoke.

---

## File map

- Create `config/rate_limits.php` — wires `AUTH_RATE_LIMIT`/`EXAM_RATE_LIMIT` (verified missing: `config('rate_limits.*')` always falls back to 1000 today, and 100 bots on one IP would eat 1000/min instantly).
- Create `.env.loadtest` — pgsql `exam_system_loadtest`, `QUEUE_CONNECTION=redis`, `BROADCAST_CONNECTION=log`, `EXAM_RATE_LIMIT=100000`, `AUTH_RATE_LIMIT=100000`.
- Create `deploy/loadtest/nginx.conf` + `deploy/loadtest/php-fpm-pool.conf` — server block → fpm socket; pool `pm=static`, `pm.max_children=30` (keeps pgsql connections < default 100).
- Create `app/LoadTesting/Scenario.php` — `forBot(int $botId, string $masterSeed): array` returning action lists (`start, status, begin, answer, violation, pause, resume, submit, submit_dup, eager_begin, double_start`); per-bot seed `hash(masterSeed.botId)` so changing `--students` doesn't reshuffle bots 1..k.
- Create `app/LoadTesting/StudentBot.php` — `run(array $actions, string $baseUrl, string $resultsPath): void`; Guzzle `CookieJar` per bot, CSRF via GET login page → `X-XSRF-TOKEN`, appends JSONL timing lines.
- Create `app/LoadTesting/TeacherBot.php` — delayed `startExam`, `warn`, `forceEnd` one session, `resumeSession`.
- Create `app/LoadTesting/RunResults.php` — `aggregate(string $dir): array` (p50/p95 per action, 5xx/429 counts, chaos-matrix coverage).
- Create `app/Console/Commands/LoadTestExamCommand.php` — signature `exams:load-test {students=100} {--seed=} {--base-url=http://localhost:8080} {--dry-run}`; fixture setup, fork/wait/reap, assert, summary table.
- Create `tests/Unit/LoadScenarioTest.php`, `tests/Unit/LoadRunResultsTest.php`, `tests/Unit/RateLimitConfigTest.php`.
- Create `docs/runbook-loadtest.md` — install, configure, run, replay, interpret.
- Modify `composer.json` — add `"test:load": "php artisan exams:load-test"`.

---

### Task 1: Wire rate-limit config (prerequisite)

**Files:**
- Create: `config/rate_limits.php`
- Test: `tests/Unit/RateLimitConfigTest.php`

**Interfaces:**
- Consumes: `env('AUTH_RATE_LIMIT')`, `env('EXAM_RATE_LIMIT')`
- Produces: `config('rate_limits.auth')`, `config('rate_limits.exam')` (already consumed by `AppServiceProvider.php:36-42`)

- [ ] **Step 1: Write the failing test**

```php
public function test_rate_limits_resolve_with_env_fallbacks(): void
{
    $this->assertEquals(
        (int) env('EXAM_RATE_LIMIT', 1000),
        config('rate_limits.exam')
    );
    $this->assertEquals(
        (int) env('AUTH_RATE_LIMIT', 1000),
        config('rate_limits.auth')
    );
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RateLimitConfigTest`
Expected: FAIL with "Undefined array key" (no `config/rate_limits.php` exists today)

- [ ] **Step 3: Write minimal implementation**

```php
return [
    'auth' => (int) env('AUTH_RATE_LIMIT', 1000),
    'exam' => (int) env('EXAM_RATE_LIMIT', 1000),
];
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=RateLimitConfigTest && php artisan test`
Expected: PASS, full suite green

- [ ] **Step 5: Commit**

```bash
git add config/rate_limits.php tests/Unit/RateLimitConfigTest.php
git commit -m "feat: wire AUTH/EXAM_RATE_LIMIT env into rate_limits config"
```

---

### Task 2: Load environment + composer script + nginx/php-fpm configs

**Files:**
- Create: `.env.loadtest`, `deploy/loadtest/nginx.conf`, `deploy/loadtest/php-fpm-pool.conf`
- Modify: `composer.json` (add `test:load` script)

**Interfaces:**
- Consumes: nothing (static config + docs)
- Produces: base URL `http://localhost:8080` + DB `exam_system_loadtest` that Tasks 5–7 run against

- [ ] **Step 1: Write `.env.loadtest`**

```ini
APP_NAME="Online Exam System"
APP_ENV=local
APP_DEBUG=false
APP_URL=http://localhost:8080
APP_KEY=  # copy from .env — same key keeps encrypted cookies valid

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=exam_system_loadtest
DB_USERNAME=user
DB_PASSWORD=change-me-in-your-local-env

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
BROADCAST_CONNECTION=log

AUTH_RATE_LIMIT=100000
EXAM_RATE_LIMIT=100000
```

- [ ] **Step 2: Write `deploy/loadtest/nginx.conf`**

```nginx
server {
    listen 8080;
    server_name localhost;
    root /home/priyabrata-das/PhpstormProjects/online-exam-system/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

- [ ] **Step 3: Write `deploy/loadtest/php-fpm-pool.conf`**

```ini
[loadtest]
user = priyabrata-das
listen = 127.0.0.1:9000
pm = static
pm.max_children = 30
pm.max_requests = 500
php_admin_value[memory_limit] = 256M
```

- [ ] **Step 4: Add composer script**

```json
"test:load": "php artisan exams:load-test"
```

- [ ] **Step 5: Verify infra manually**

Run: `sudo apt install -y nginx php8.4-fpm`, link the two configs, `sudo systemctl reload nginx php8.4-fpm`, `redis-server --daemonize yes`, `curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/up`
Expected: `200`

- [ ] **Step 6: Commit**

```bash
git add .env.loadtest deploy/loadtest/nginx.conf deploy/loadtest/php-fpm-pool.conf composer.json
git commit -m "chore: load-test env, nginx/php-fpm configs, test:load script"
```

---

### Task 3: Seeded Scenario builder (unit-tested)

**Files:**
- Create: `app/LoadTesting/Scenario.php`
- Test: `tests/Unit/LoadScenarioTest.php`

**Interfaces:**
- Consumes: `(int $botId, string $masterSeed)`
- Produces: `Scenario::forBot(int $botId, string $masterSeed): array<int, array{action: string, ...}>` — consumed by `StudentBot` (Task 4) and `--dry-run` (Task 5)

- [ ] **Step 1: Write the failing tests**

```php
public function test_same_bot_and_seed_replays_identically(): void
{
    $this->assertSame(Scenario::forBot(3, 's1'), Scenario::forBot(3, 's1'));
}

public function test_action_pool_covers_all_endpoints_across_100_bots(): void
{
    $seen = [];
    foreach (range(1, 100) as $id) {
        foreach (Scenario::forBot($id, 'cover') as $step) {
            $seen[$step['action']] = true;
        }
    }
    foreach (['start', 'status', 'begin', 'answer', 'violation', 'pause', 'resume', 'submit', 'submit_dup'] as $action) {
        $this->assertArrayHasKey($action, $seen);
    }
}
```

- [ ] **Step 2: Run to verify they fail**

Run: `php artisan test --filter=LoadScenarioTest`
Expected: FAIL with "Class App\LoadTesting\Scenario not found"

- [ ] **Step 3: Write minimal implementation**

```php
namespace App\LoadTesting;

class Scenario
{
    public static function forBot(int $botId, string $masterSeed): array
    {
        mt_srand(crc32($masterSeed.'.'.$botId));
        $steps = [['action' => 'double_start'], ['action' => 'eager_begin']];
        // bots 0-4 carry the scripted chaos matrix; the rest roll dice
        // ... weighted loop: answer 60, status 15, violation 8, pause/resume 5 ...
        $steps[] = ['action' => 'submit'];
        $steps[] = ['action' => 'submit_dup'];
        return $steps;
    }
}
```
(Full weight table + chaos assignment finalized in the task; the two tests above pin the contract.)

- [ ] **Step 4: Run to verify they pass**

Run: `php artisan test --filter=LoadScenarioTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/LoadTesting/Scenario.php tests/Unit/LoadScenarioTest.php
git commit -m "feat: seeded per-bot load-test scenario builder"
```

---

### Task 4: StudentBot + TeacherBot

**Files:**
- Create: `app/LoadTesting/StudentBot.php`, `app/LoadTesting/TeacherBot.php`

**Interfaces:**
- Consumes: `Scenario::forBot()` output; Guzzle `CookieJar` per bot; CSRF via GET `/login` → `X-XSRF-TOKEN` header
- Produces: `StudentBot::run(array $actions, string $baseUrl, string $resultsPath): void`, `TeacherBot::run(string $baseUrl, int $examId): void` — consumed by the command (Task 5)

- [ ] **Step 1: Write `StudentBot::run()`** — login with the shared known password, execute each action step, wrap every request with `microtime(true)`, append one JSONL line per action: `{"bot":1,"action":"answer","status":200,"ms":42}`. Never throw on 5xx — record it as data.
- [ ] **Step 2: Write `TeacherBot::run()`** — sleep 20s (bots' `eager_begin` steps must get 4xx first), then `startExam`, `warn` 2 sessions, `forceEnd` 1 session, `resumeSession` 1.
- [ ] **Step 3: Verify via Task 6 smoke run** (no isolated HTTP test for bots — stated explicitly, not a placeholder: process-level HTTP is covered by `--dry-run` + 10-student smoke).
- [ ] **Step 4: Commit**

```bash
git add app/LoadTesting/StudentBot.php app/LoadTesting/TeacherBot.php
git commit -m "feat: student and teacher load-test bots"
```

---

### Task 5: `exams:load-test` command

**Files:**
- Create: `app/Console/Commands/LoadTestExamCommand.php`, `app/LoadTesting/RunResults.php`
- Test: `tests/Unit/LoadRunResultsTest.php` (aggregation math: p50/p95, 5xx/429 counts, chaos-coverage)

**Interfaces:**
- Consumes: `Scenario`, `StudentBot`, `TeacherBot`, `RunResults::aggregate(string $dir): array`
- Produces: `php artisan exams:load-test {students=100} {--seed=} {--base-url=http://localhost:8080} {--dry-run}`

- [ ] **Step 1: Write `RunResults` failing test** — feed a fixed JSONL fixture, assert exact p50/p95 and counts.
- [ ] **Step 2: Implement `RunResults::aggregate()`** to pass.
- [ ] **Step 3: Write the command** — fixture: `migrate:fresh`, 1 teacher, 1 published exam (10 `mcq_single` with known answers), `time_limit=120` (expiry scheduler can't interfere), `max_attempts=5`, N students sharing one pre-hashed password (avoids 100× bcrypt on setup). `--dry-run` prints the fixture plan + bot 1's full action list and exits without forking.
- [ ] **Step 4: Fork/wait/reap** — `pcntl_fork` per bot (child runs bot, `exit(0)`), parent `pcntl_waitpid` loop with 10-minute timeout, `posix_kill` stragglers, record child exit codes.
- [ ] **Step 5: Verify `--dry-run` output by hand**, run `LoadRunResultsTest`, commit.

```bash
git add app/Console/Commands/LoadTestExamCommand.php app/LoadTesting/RunResults.php tests/Unit/LoadRunResultsTest.php
git commit -m "feat: exams:load-test command with fork orchestration"
```

---

### Task 6: Assertions (correctness, not just 200s)

**Files:**
- Modify: `app/Console/Commands/LoadTestExamCommand.php` (post-run assertion block)

- [ ] **Step 1: Implement assertions** (each a named method returning `['name', 'pass', 'detail']`):
  1. `5xx == 0` and `429 == 0` — any 429 means the run measured the limiter, fail loudly.
  2. Duplicate submit returns the identical score (compare the bot's two `submit` response bodies).
  3. `double_start` never yields a 2nd active session per (exam, student) — direct DB count.
  4. Pause→resume: `remaining_time` after resume ≥ before pause minus elapsed tolerance.
  5. ≥5 `eager_begin` rejected pre-start; ≥2 violation-spams end `terminated`; chaos minimums met (double-start ×5, dup-submit ×100, pause/resume ×3, forceEnd ×1).
  6. Grading spot-check: 5 random completed sessions recomputed via `GradingService`, exact match.
  7. Queues drained: `jobs` + `failed_jobs` empty within 60s of last action.
- [ ] **Step 2: Report latencies** — p50/p95 per action printed as a table; starting budgets (tune after first run): `status` p95 <500ms, `answer` p95 <1s, submit-ack p95 <2s (ack only enqueues `GradeExamSession`).
- [ ] **Step 3: Non-zero exit code on any assertion failure.** Commit.

```bash
git add app/Console/Commands/LoadTestExamCommand.php
git commit -m "feat: load-test correctness assertions and latency report"
```

---

### Task 7: Runbook + verification runs

**Files:**
- Create: `docs/runbook-loadtest.md`

- [ ] **Step 1: Write the runbook** — services to start (`nginx`, `php8.4-fpm`, `redis-server`, `php artisan queue:work --queue=default`), `composer run test:load -- --students=10 --seed=1`, full `100 --seed=<n>`, seed-replay recipe, thresholds table, "429/5xx triage" section.
- [ ] **Step 2: Execute** — `--dry-run` → 10-student smoke → full 100. Record seed + results table in the runbook.
- [ ] **Step 3: Final gates** — `composer run test` green, `./vendor/bin/phpstan analyse` clean, `./vendor/bin/pint --test` on touched files. Commit.

```bash
git add docs/runbook-loadtest.md
git commit -m "docs: load-test runbook with first-run results"
```

---

## Self-review

- Spec coverage: 100 concurrent (forked procs + fpm/nginx) ✓; randomness (seeded Scenario + replay) ✓; answer saving, violations, pause/resume, lobby (`status` polls + `eager_begin` gating), other endpoints (teacher warn/forceEnd/resume/startExam) ✓; real-scenario ✓.
- No placeholders: every task names files, signatures, exact commands, assertion thresholds.
- Type consistency: `Scenario::forBot(int, string): array`, `StudentBot::run(array, string, string): void`, `RunResults::aggregate(string): array` used uniformly.
