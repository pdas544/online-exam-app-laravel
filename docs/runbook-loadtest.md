# Load-test runbook — `exams:load-test` harness

Operator guide for the exam load-test harness (`app/Console/Commands/LoadTestExamCommand.php`,
`app/LoadTesting/`, runner `scripts/load-test.sh`). Copy-pasteable commands. Live
first-run numbers are recorded in [First-run results](#first-run-results).

## Prerequisites

One-time infra setup (controller-owned — do NOT run these yourself unless you own the box):

```bash
sudo apt install -y nginx php8.4-fpm redis-server
sudo ln -s /home/priyabrata-das/PhpstormProjects/online-exam-system/deploy/loadtest/nginx.conf /etc/nginx/sites-enabled/loadtest
sudo ln -s /home/priyabrata-das/PhpstormProjects/online-exam-system/deploy/loadtest/php-fpm-pool.conf /etc/php/8.4/fpm/pool.d/loadtest.conf
sudo systemctl reload nginx php8.4-fpm
redis-server --daemonize yes
sudo -u postgres psql -c "CREATE DATABASE exam_system_loadtest OWNER newuser;"   # once
```

The load DB must exist with `newuser` as owner: the wrapper bakes `DB_USERNAME=newuser`
into its generated merge (password reused from your dev `.env`), so a DB owned by
`postgres` or another role will fail auth at run time.

What the configs do: `deploy/loadtest/nginx.conf` serves `public/` on port `8080`
(`APP_URL=http://localhost:8080`); `deploy/loadtest/php-fpm-pool.conf` runs pool
`[loadtest]` on `127.0.0.1:9000` (`pm = static`, `pm.max_children = 30`).
Raised rate limits (`AUTH_RATE_LIMIT=100000`, `EXAM_RATE_LIMIT=100000`) come from the
wrapper's generated `.env` merge at run time — never run the harness bare against
the dev `.env` (see below).

## Starting a run

The REQUIRED entry point is the wrapper — never invoke `artisan exams:load-test`
directly for a live run:

1. **Smoke** (10 students, seed 1):

   ```bash
   ./scripts/load-test.sh 10 --seed=1
   ```

2. **Full** (100 students, seed 100):

   ```bash
   ./scripts/load-test.sh 100 --seed=100
   ```

   NOTE: the student count is positional (`100`) — never `--students=100`
   (unknown option; the signature is `exams:load-test {students=100} {--seed=} {--base-url=http://localhost:8080} {--dry-run}`,
   and the wrapper forwards `$@` verbatim).

What the wrapper owns (so you don't do any of this by hand):

- Swaps `.env` for a generated load merge (dev `.env` minus overridden keys, plus
  `DB_DATABASE=exam_system_loadtest`, `DB_USERNAME=newuser`, redis queue,
  `APP_URL=http://localhost:8080`, raised rate limits). A file swap — not exports —
  because php-fpm only reads the `.env` FILE per request and clears its environment.
- Migrates, flushes redis (`queue:clear`), then starts a SUPERVISED queue worker
  (`queue:work --sleep=1 --tries=1` in a restart-on-crash loop, pattern-scoped so dev
  workers are never touched). There is no standalone worker step — the wrapper owns it.
- Runs the harness, then restores your original `.env` via trap (also on INT/TERM).
  Refuses to start if `.env.before-loadtest` already exists (a previous run did not
  restore — inspect and remove it, then retry), and aborts if nginx+php-fpm are not
  answering `http://localhost:8080/up`.

Dry-run sanity (no DB, no forks) — verifies flag parsing and prints bot 0's
scripted action list:

```bash
php artisan exams:load-test 3 --dry-run --seed=doccheck
```

## Replay recipe

Same seed + same student count reproduces the run: bot ids are 0-based (bot `0..N-1`
for N students), and each bot's script is derived from its per-bot seed
`hash(masterSeed.botId)` (`Scenario::forBot()` reseeds with
`crc32($masterSeed.'.'.$botId)`), so replay is stable per bot id — bot *k* always
plays the same sequence for a given master seed regardless of how many bots run.
Changing the count appends new bots without reshuffling existing ones — e.g. seed
`1` with 10 students and seed `1` with 100 students share bots 0..9 action-for-action.

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
| `submit_idempotent` | every bot with ≥2 submit lines: identical bodies with matching statuses (200+200, or identical rejections — e.g. 400+400 when terminated/paused sessions legitimately reject twice) |
| `no_duplicate_active_sessions` | no student holds >1 active (`scheduled`/`in_progress`/`paused`) session |
| `eager_begin_rejected` | rejected `eager_begin` count ≥ 5 |
| `spam_terminates` | bot-0 (`loadtest-student-0@…`) latest session status is `terminated` |
| `grading_spot_check` | first up to 5 completed sessions recompute exactly (2-decimal rounding); explicit FAIL on null score, and FAIL on zero completions |
| `queues_drained` | `Queue::size()` and `failed_jobs` both 0 within 60 s (honors the redis driver — never the `jobs` table directly) |
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

- **429s** → the raised limits were not active: the harness was run directly instead
  of via `./scripts/load-test.sh` (only the wrapper's generated merge sets
  `AUTH_RATE_LIMIT=100000` / `EXAM_RATE_LIMIT=100000`). Dev `.env` rate limits will
  throttle 100 bots.
- **5xx** → read `storage/logs/laravel.log` on the load env for the stack trace.
- **nginx 404 "File not found"** on every route → home-dir traverse: php-fpm
  (`www-data`) must be able to stat the docroot path:

  ```bash
  chmod 751 ~
  ```

- **Grading failures** (`grading_spot_check` FAIL, scores null) → grading is async
  (`GradeExamSession`) and the wrapper's supervised worker must have been up for the
  whole run — check `.superpowers/sdd/2026-09-08-exam-load-test/worker.log` for
  crashes/restarts, then re-run (each run starts `migrate:fresh`, so nothing to clean).
- **`queues_drained` timeout** (`jobs=N`/`failed_jobs=N` after 60 s) → worker dead
  or redis down (`redis-server --daemonize yes`, then check `worker.log`).

## First-run results

| seed | students | 5xx | 429 | submit p95 | answer p95 | status p95 | verdict |
|---|---|---|---|---|---|---|---|
| 100 | 100 | 0 | 0 | 389.56ms | 445.21ms | 409.70ms | 9/9 PASS |
| 6 | 10 | 0 | 0 | ≈30ms | ≈59ms | ≈35ms | 9/9 PASS |

## Cleanup

- Fixtures live in the `exam_system_loadtest` database; every run starts with
  `migrate:fresh`, so runs are self-cleaning — no manual teardown. The wrapper also
  restores your original `.env` via trap after the run.
- Per-bot result JSONL lands under `storage/app/loadtest/<timestamp>-<seed>/`
  (`bot-*.jsonl` + `teacher.jsonl`). Yes, gitignored: `storage/app/.gitignore`
  wildcards everything under `storage/app/` except `private/`, `public/`, and
  itself (root `.gitignore` does not list the path — the directory-level one
  covers it). Safe to leave; delete old timestamped dirs to reclaim disk.
- Supervised worker output streams to
  `.superpowers/sdd/2026-09-08-exam-load-test/worker.log` (untracked working file,
  not committed).
