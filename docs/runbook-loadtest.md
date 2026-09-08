# Load-test runbook — `exams:load-test` harness

Operator guide for the exam load-test harness (`app/Console/Commands/LoadTestExamCommand.php`,
`app/LoadTesting/`). Copy-pasteable commands. Live first-run numbers live in
[First-run results](#first-run-results) (controller fills the TBD row).

## Prerequisites

One-time infra setup (controller-owned — do NOT run these yourself unless you own the box):

```bash
sudo apt install -y nginx php8.4-fpm redis-server
sudo ln -s /home/priyabrata-das/PhpstormProjects/online-exam-system/deploy/loadtest/nginx.conf /etc/nginx/sites-enabled/loadtest
sudo ln -s /home/priyabrata-das/PhpstormProjects/online-exam-system/deploy/loadtest/php-fpm-pool.conf /etc/php/8.4/fpm/pool.d/loadtest.conf
sudo systemctl reload nginx php8.4-fpm
redis-server --daemonize yes
sudo -u postgres createdb exam_system_loadtest   # once
```

What the configs do: `deploy/loadtest/nginx.conf` serves `public/` on port `8080`
(`APP_URL=http://localhost:8080`); `deploy/loadtest/php-fpm-pool.conf` runs pool
`[loadtest]` on `127.0.0.1:9000` (`pm = static`, `pm.max_children = 30`).
Raised rate limits (`AUTH_RATE_LIMIT=100000`, `EXAM_RATE_LIMIT=100000`) come from
`.env.loadtest` — only active when the run actually loads that file (see below).

## Starting a run

1. **Queue worker FIRST**, in its own terminal, left running for the whole run:

   ```bash
   APP_KEY=$(grep ^APP_KEY= .env | cut -d= -f2) php artisan queue:work --env=loadtest --sleep=0 --tries=1
   ```

2. **Smoke** (10 students, seed 1):

   ```bash
   APP_KEY=$(grep ^APP_KEY= .env | cut -d= -f2) composer run test:load -- 10 --seed=1
   ```

   NOTE: the student count is positional (`10`) — never `--students=10`
   (unknown option; the signature is `exams:load-test {students=100} {--seed=} {--base-url=http://localhost:8080} {--dry-run}`).

3. **Full** (100 students, pick a seed):

   ```bash
   APP_KEY=$(grep ^APP_KEY= .env | cut -d= -f2) composer run test:load -- 100 --seed=<n>
   ```

Why this shape:

- `--env=loadtest` loads `.env.loadtest` (load DB `exam_system_loadtest`,
  redis queue, raised rate limits, `APP_URL=http://localhost:8080`). Without it
  you run against the dev DB with dev rate limits — and `429/5xx triage` below
  is the first place you'll feel it.
- The `APP_KEY=$(grep ^APP_KEY= .env | cut -d= -f2)` prefix injects the real app
  key from your local `.env` without dirtying the committed `.env.loadtest`
  template, whose `APP_KEY` stays empty. Encrypted session cookies only decrypt
  if the web worker (php-fpm) and the bot/queue processes share the key.
- Dry-run sanity (no DB, no forks) — verifies flag parsing and prints bot 0's
  scripted actions:

  ```bash
  php artisan exams:load-test 3 --dry-run --seed=doccheck
  ```

## Replay recipe

Same seed + same student count reproduces the run: each bot's script is derived
from its per-bot seed `hash(masterSeed.botId)` (`Scenario::forBot()` reseeds with
`crc32($masterSeed.'.'.$botId)`), so bot *k* always plays the same sequence for a
given master seed regardless of how many bots run. Changing the count appends
new bots without reshuffling existing ones — e.g. seed `1` with 10 students and
seed `1` with 100 students share bots 0..9 action-for-action.

Fixed role bots (independent of seed randomness): bot 0 is the spammer (double
`tab_switch` spam → expected `terminated`), bot 1 is the pauser (3×
pause/resume), bots 2..4 run the short answer/violation script, bots ≥5 run the
weighted random middle. Fixture is uniform every run: 1 teacher, 1 published exam,
10 `mcq_single` questions (options `A/B`, correct `['B']`, 5 pts each).

## Thresholds table

Hard fail — any FAIL fails the run (`0` tolerance unless noted):

| Assertion | Pass condition |
|---|---|
| `no_server_errors` | `http_5xx = 0` |
| `no_rate_limit_hits` | `http_429 = 0` |
| `submit_idempotent` | every bot with ≥2 submit lines: all status 200, bodies identical |
| `no_duplicate_active_sessions` | no student holds >1 active (`scheduled`/`in_progress`/`paused`) session |
| `eager_begin_rejected` | rejected `eager_begin` count ≥ 5 |
| `spam_terminates` | bot-0 (`loadtest-student-0@…`) latest session status is `terminated` |
| `grading_spot_check` | first 5 completed scored sessions recompute exactly (2-decimal rounding) |
| `queues_drained` | `jobs` and `failed_jobs` both 0 within 60 s |
| `chaos_minimums` | `pause ≥ 3`, `submit_dup ≥ students`, `warn ≥ 2`, `end ≥ 1` |

Soft budgets — informational WARN only, never FAIL:

| Probe | Budget |
|---|---|
| `status` p95 | < 500 ms |
| `answer` p95 | < 1 s |
| submit-ack (`submit`) p95 | < 2 s |

## Minimum size

Runs with fewer than 5 students fail `eager_begin_rejected`/`pause` minimums BY
CONSTRUCTION — every bot emits exactly one `eager_begin` (rejected when no session
exists yet), and the assertion needs ≥ 5 rejected. Use ≥ 5 students; smoke uses 10.

## 429/5xx triage

- **429s** → the raised limits were not active: check the run really loaded
  `.env.loadtest` via `--env=loadtest` (worker terminal AND artisan invocation).
  Dev `.env` rate limits will throttle 100 bots.
- **5xx** → read `storage/logs/laravel.log` on the load env for the stack trace.
- **Grading failures** (`grading_spot_check` FAIL, scores null) → the queue worker
  was not running BEFORE the run. Grading is async (`GradeExamSession`); start the
  worker first, then re-run (each run starts `migrate:fresh`, so nothing to clean).
- **`queues_drained` timeout** (`jobs=N`/`failed_jobs=N` after 60 s) → worker dead
  or redis down (`redis-server --daemonize yes`, then check the worker terminal).

## First-run results

| seed | students | 5xx | 429 | submit p95 | answer p95 | status p95 | verdict |
|---|---|---|---|---|---|---|---|
| TBD — controller fills after live runs | | | | | | | |

## Cleanup

- Fixtures live in the `exam_system_loadtest` database; every run starts with
  `migrate:fresh`, so runs are self-cleaning — no manual teardown.
- Per-bot result JSONL lands under `storage/app/loadtest/<timestamp>-<seed>/`
  (`bot-*.jsonl` + `teacher.jsonl`). Yes, gitignored: `storage/app/.gitignore`
  wildcards everything under `storage/app/` except `private/`, `public/`, and
  itself (root `.gitignore` does not list the path — the directory-level one
  covers it). Safe to leave; delete old timestamped dirs to reclaim disk.
