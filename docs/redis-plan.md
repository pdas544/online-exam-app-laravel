# Redis + Queue Plan — single VPS, self-hosted Redis, 100 submits, async grading

> Scope: single-server deploy, self-hosted `redis-server` on the same VPS, 100 simultaneous submits,
> async grading with dashboard / My Results notification. Reverb single-node (no scaling).

## 1. What exists today vs what each tech does

| | Existing queue (`database`) | Redis | Reverb |
|---|---|---|---|
| What it is | DB `jobs` table + `queue:listen`. One job: `app/Jobs/GradeExamSession.php:11 ShouldQueue`, dispatched in `app/Services/ExamSessionService.php:94,122`. Tests use `sync` (`phpunit.xml:29`). | In-memory store for cache / sessions / queues / locks / rate-limits / pub-sub. Defined but unused: `config/cache.php:75-79`, `config/queue.php:67-74`, `config/database.php:145-181`. Defaults are `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database` (`.env.example:61,38,29`). No `predis`/phpredis ext in `composer.json`. | WebSocket server for live push. Events already `ShouldBroadcast` (`app/Events/BaseExamEvent.php:11` → `PrivateChannel exam.{id}/teacher.{id}`, plus `student.{id}`), `routes/channels.php:7-29` ready, but `BROADCAST_CONNECTION=log` (`.env.example:36`, `REVERB_*` commented) so broadcasts just log. Needs worker + `reverb:start`. |
| Solves | Defers slow work off request (grading). | Hot-read speed, write-burst absorption, atomic locks, shared throttle state. | Sub-second teacher/student UI updates. Does **not** replace queue/cache. |
| Doesn't solve | Speed (polls DB, row locks under burst); `after_commit:false` everywhere. | Durability (volatile unless AOF/RDB); needs ops. | Persistence, grading, idempotency. |

Mental model: **queue = do later, Redis = remember fast + lock fast, Reverb = push now.**

## 2. Where to include each (concurrency lens)

### Redis — 6 areas, highest value first
1. **Queue backend** (`QUEUE_CONNECTION=redis`): grading + new violation/broadcast jobs stop hammering pgsql `jobs` table during 100-student submit bursts.
2. **Atomic start/submit locks** (`Cache::lock("start:{exam}:{student}",10)->block(5)` in `ExamSessionService::start:27-45`; `lock("submit:{session}")`): kills double-`start` race (check-then-create has no DB unique).
3. **Throttle store** (`throttle:exam` + `RateLimiter` on `saveAnswer/violation/status`): DB throttle doesn't share across workers; Redis does (matters even on one box with multiple php-fpm workers).
4. **Hot-read cache**: exam paper (`ExamService:16 remember 600s` — currently dead, wire into take flow), `subjects.all 3600s`, dashboard availability `300s` (already in `DashboardService:70-71`).
5. **Session driver** (`SESSION_DRIVER=redis`): **skipped** — single VPS stays on `database`, zero migration risk.
6. **Reverb scaling pub-sub** (`REVERB_SCALING_ENABLED=true`): **skipped** — single Reverb node needs nothing.

### Queue — jobs on separate queues
1. `GradeExamSession` (keep, move to `grading` queue, `after_commit:true`, retries 3 + `failed()` alert) — already exists.
2. **New `LogExamViolation`** (`violations` queue): `ViolationService::record` + `ExamSession::logViolation` increment + pause + `ViolationDetected` broadcast move off the `saveAnswer` request path.
3. **Broadcasts** (`broadcasts` queue): `StudentJoined/ExamEnded/TeacherWarning/ExamForceEnded` stay event-driven via `ShouldBroadcast`, worker delivers to Reverb.
4. **Scheduled `ExpireExamSessions`** (existing `routes/console.php:9 everyMinute`) stays sync (cheap `chunkById(50)`).

Explicitly **not** queued: `saveAnswer` itself (students need instant confirmation; queue adds loss risk) — debounce + Redis lock + single `update` instead. `start` session create stays sync inside transaction + Redis lock.

### Async grading notification contract
- `submit` flips status sync, dispatches `GradeExamSession`; student sees **`grading_pending`** (`completed + score null` → "Grading… check My Results"), never a premature fail.
- New `GradingCompleted` event on `PrivateChannel student.{id}` after score save → student dashboard toast + badge; My Results is source of truth.

## 3. Implementation tasks

### Task 0 — prereqs + baseline (no behavior change)
- `composer.json`: add `predis/predis` (pure-PHP, avoids `ext-redis` install pain).
- VPS: `apt install redis-server`, enable, `maxmemory 256mb`, `appendonly yes`, `noeviction` (queues must never drop). Separate DBs: cache DB 0, queue DB 1.
- `composer dev` documents `redis-server` + `queue:work --queue=grading,violations,broadcasts,default`.
- Record baseline `exams:load-test` p95/queries before changes. `redis-cli ping` in dev.
- Test: `composer run test` green.

### Task 1 — Redis wiring, fail-safe
- `config/cache.php:94-100`: failover `redis → database` so Redis-down never 500s.
- `config/queue.php:67-74`: redis conn `after_commit:true`, `retry_after:90`, `block_for:5`.
- `config/database.php`: redis timeouts/read_timeout.
- `.env.example:38,61,66-69`: commented Redis recipe (`QUEUE_CONNECTION=redis`, `CACHE_STORE`, `REDIS_*`, `REDIS_DB`).
- Keep prod default `database` until Task 3 proven; flip by env only.
- Test: suite green on `array/sync`; manual `CACHE_STORE=redis Cache::put/get`.

### Task 2 — start/submit idempotency locks (race fix)
- `ExamSessionService::start:27-45`: wrap check-then-create in `Cache::lock("exam-start:{$exam->id}:{$studentId}",10)->block(5, ...)` + existing `DB::transaction`.
- `submit:110-124`: add `lock("submit:{session}")`.
- Migration: partial unique `(exam_id,student_id)` where status in `(scheduled,in_progress,paused)` — pgsql partial index, sqlite-compatible no-op in tests.
- Test: new `tests/Feature/ConcurrentStartTest.php` — 10 parallel starts → exactly 1 session (fails before, passes after); double-submit → single grading dispatch.

### Task 3 — queue split
- `GradeExamSession`: `->onQueue('grading')`, `$tries=3`, `backoff 10/30/60`, keep `failed()` log + alert.
- Create `app/Jobs/LogExamViolation.php` (`ShouldQueue`, `violations` queue).
- `ExamSessionController::logViolation:195-226` becomes `dispatch()` only.
- Events: `$broadcastQueue='broadcasts'` on `BaseExamEvent` subclasses.
- Runtime: single `queue:work --queue=grading,violations,broadcasts,default --tries=3 --backoff=10` systemd unit (autorestart, memory-limit). `failed_jobs` triage via `DashboardService::adminHealth:291-296`.
- Test: `Queue::fake()` asserts violation→job, submit→grading job; sync suite passes.

### Task 4 — grading-complete notification
- Create `app/Events/GradingCompleted.php` (`PrivateChannel student.{id}`, studentId + examId + score/passed).
- `GradeExamSession::handle:17-32`: broadcast after score save.
- `StudentDashboardController` + `results/index.blade.php`: `grading_pending` banner ("Grading… check My Results"), Echo `student.{id}` toast + badge; My Results is source of truth.
- Test: job test asserts broadcast fired + dashboard shows pending, then completed shows score/passed.

### Task 5 — hot-read cache + throttle on Redis
- Wire `ExamService:16` paper cache 600s into take/start + `forget` on `updatePoints/reorder/add/remove` (invalidate hook is currently never called — fix that).
- `SubjectService:15` 3600s; keep `DashboardService:70-71` 300s.
- `config/rate_limits.php + AppServiceProvider:36-41`: `EXAM 120/min/IP`, answers `60/min/session`, store Redis.
- Test: hit/invalidation tests; 429 spam test; Redis-down → failover DB, no 500.

### Task 6 — Reverb single-node live (monitor + grading toast), last
- `.env.example:44-59`: fill recipe (`BROADCAST_CONNECTION=reverb`, host/port/scheme), `config/reverb.php:85` lock `allowed_origins` to `APP_URL` (not `*`), `resources/js/bootstrap.js` `VITE_REVERB_*`; keep `routes/channels.php`; scaling stays `false`.
- Test: `Event::fake()` broadcast assertions; manual 2-browser (student submit → teacher monitor + student toast).

### Task 7 — single-VPS ops runbook
- `docs/runbook.md`: Redis persistence note (cache rebuildable, queues drainable), pgsql backup, `/up` health, `queue:failed` triage, queue-depth alert threshold on `adminHealth` counts.
- Load gate: re-run 100-submit test — target p95 submit-status <500ms, grading drain <2min, zero double-sessions.

## 4. Tradeoffs taken
- Sessions stay `database` — correct for a single box, zero migration risk.
- No Reverb Redis scaling — a single node doesn't need it.
- `saveAnswer` not queued — instant ACK beats queue-loss risk at 100 concurrency; burst absorbed by locks + indexes instead.

## 6. Task 7 live gate (2026-09-11, pre-redis baseline on main checkout)

nginx+php-fpm serves the MAIN checkout, so the live gate below measures
pre-Tasks-0–6 code — the true baseline the Task 0 smoke run failed to
capture. Re-run post-merge for the comparison.

- First attempt INVALID: wrapper's worker-log dir was missing, so the
  supervised worker never started → 407 jobs backlogged, grading spot-check
  failed. Fixed by `mkdir -p` for the log dir (now in `scripts/load-test.sh`).
- Second run (`scripts/load-test.sh 100 --seed=100`): **all 9 gates PASS** —
  no 5xx, no 429, submit idempotent, no duplicate sessions, spam terminates,
  grading 5/5 correct, queues drained in ~1s, chaos minimums met.
- Latencies (p95): submit-ack 465ms (<2s ✓), answer 540ms (<1s ✓),
  status 531ms (WARN line 500ms, marginal — first run measured 436ms, so
  treat 500ms as noise band, not regression), login 1199ms (bcrypt, expected).

## 7. Post-merge gate (2026-09-11, WITH Tasks 0–6 + read_timeout fix)

Two runs were needed — the first exposed a real Task 1 bug, the second proves
the full path:

- Run 1: grading drained but violations/broadcasts stranded (542 jobs, zero
  failures). Root cause: `REDIS_READ_TIMEOUT=5s` equalled
  `REDIS_QUEUE_BLOCK_FOR=5s`, so phpredis aborted the blocking pop on the
  first empty queue and the multi-queue worker looped there forever
  (single-queue workers were unaffected, which is why it looked
  queue-specific). Fixed: default `read_timeout` 60s + invariant test
  (`RedisQueueConfigTest`) + runbook rule.
- Run 2: **all 9 gates PASS** — spam auto-terminates through the async
  violation job, grading 5/5, all four queues drained in ~6s.
- Latencies (p95): submit-ack 488ms, answer 525ms, status 512ms (same noise
  band as baseline), login 1240ms. Verdict: Redis path matches baseline
  latency while adding locks, async violations, and honest queue health.

## 5. Task 0 baseline (recorded 2026-09-10, worktree `feat/redis-queue`)- Dep: `predis/predis ^3.6` added (`composer.json`), `Predis\Client()->ping()` → PONG against local Redis 8.0.2
  (`maxmemory 0`, `appendonly no`, `maxmemory-policy noeviction` — Task 7 should set `maxmemory 256mb` + `appendonly yes`).
- Suite: 115 passed (838 assertions) with predis installed — no behavior change.
- In-process perf: `SubmitLoadTest` 100 simultaneous submits ~0.4–0.6s, query budget respected.
- Live 100-baseline: **blocked, rig misconfigured** (do not treat the 2026-09-10 smoke run as a baseline —
  bots got 404 on `/login`, zero completions). Findings to fix before the Task 7 gate:
  1. `php artisan reverb:start --port=8080` (from `composer run dev`) is squatting on port 8080, which
     `deploy/loadtest/nginx.conf` also claims — **port conflict**: default `REVERB_PORT=8080` collides with the
     load-test URL. Move Reverb (e.g. 8081) or document that `dev` and load-test runs are mutually exclusive.
  2. `php8.4-fpm` is down (nothing on 127.0.0.1:9000), so nginx `:8080` can never serve Laravel even with the
     port free. Start the `loadtest` pool before any live run.
- No dev-DB pollution from the invalid smoke run (bots never authenticated; 0 bot users/sessions in `exam_system`).
