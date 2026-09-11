# Operations runbook — online-exam-system

## Required processes (all four, or realtime silently degrades)

| Process | Command | Notes |
|---|---|---|
| Web | `php artisan serve` (dev) or nginx + php-fpm | :8000 in dev |
| Queue | `queue:work redis --queue=grading,violations,broadcasts,default --tries=3` (per-queue programs) | via supervisor: `deploy/supervisor.exam-system.conf` |
| Reverb | `reverb:start --port=8080` | Browsers hit `ws(s)://host:8080` |
| Scheduler | cron `* * * * *` → `schedule:run` | Drives `exams:expire-sessions` every minute |

`composer run dev` covers web + queue + logs + Vite only — Reverb and the
scheduler must be started separately (see `codebase-summary` §5).

## Failure stories

- **Grading job fails** (`GradeExamSession`, 3 tries): `failed()` logs
  `grading.failed` with session/exam IDs. The session stays `completed` with
  a null score; the next submit POST re-dispatches grading (idempotent
  recovery — no admin action needed, but watch the log).
- **Queue down**: broadcasts (`ShouldBroadcast`) pile in `jobs`; lobby
  Proceed never unlocks, monitor goes stale. Check `queue:work` first —
  this was the root cause of the first 2-browser failure.
- **Reverb down**: Echo `ERR_CONNECTION_REFUSED` in browser console; same
  symptoms as queue down. Check `:8080` listener.
- **Port clash on :8080**: `reverb:start --port=8080` and the load-test
  nginx (`deploy/loadtest/nginx.conf`) claim the same port and are mutually
  exclusive. A squatting Reverb answers `/up` but 404s everything else —
  `scripts/load-test.sh` refuses the run when `GET /login != 200`; stop the
  squatter (or move `REVERB_PORT`) before load-testing.
- **Scheduler down**: timed-out sessions sit `in_progress` forever. Check
  cron; `schedule:list` shows registered commands.

## Backup & restore

- Nightly: `scripts/backup.sh` via cron (`0 2 * * *`). 14-day retention,
  `pg_dump` custom format into `$BACKUP_DIR`.
- Restore: `pg_restore -h … -U … -d exam_system --clean <file>`, then
  `php artisan migrate --force` to confirm schema currency.
- TODO (open): offsite sync of `$BACKUP_DIR` — currently host-local only.

## Redis (single VPS, self-hosted)

- Queues live in DB 0, cache in DB 1 (`REDIS_DB` / `REDIS_CACHE_DB`).
  Cache is rebuildable by design — any `Cache::flush()` or key loss only
  costs queries, never correctness. Queues are drainable but NOT
  reconstructible: a lost `grading` job leaves its session `completed`
  with a null score until the next submit POST re-dispatches it.
- Keep `maxmemory 256mb`, `maxmemory-policy noeviction` (queues must never
  drop), `appendonly yes` so a restart doesn't orphan in-flight grading.
- If Redis is down, the app keeps serving: the `failover` cache falls back
  to database, and `adminHealth.queue_depth` falls back to the DB `jobs`
  count. Queue workers crash-loop until Redis returns — `supervisorctl
  status` shows them `BACKOFF`; no data action needed beyond restoring Redis.

## Queue triage & alert thresholds (`/admin/metrics`)

| Signal | Healthy | Investigate | Act now |
|---|---|---|---|
| `queue_depth` (grading+violations+broadcasts+default) | 0 between exams | > 50 for > 5 min (worker slow or down) | > 200 or growing: restart workers, check Redis memory |
| `failed_jobs` | 0 | > 0 (inspect `queue:failed`, `grading.failed` / `violation.failed` logs) | re-`queue:retry` after fixing the cause |
| `ungraded_completions` | 0 outside exam windows | > 0 for > 15 min (grading stuck — but submit re-dispatches, so this usually self-heals) | > 20: check `grading` worker + `failed_jobs` |

- `queue:failed` shows the exception per job; `queue:retry all` re-queues.
- Load gate before any exam day: `./scripts/load-test.sh 100 --seed=100`
  must report submit-ack p95 < 2s, grading drain < 2 min after the last
  submit, and zero duplicate active sessions.

## Deploy checklist

1. Copy `.env.production.example` → `.env`, fill secrets, `key:generate`
   (fresh installs only).
2. `composer install --no-dev --optimize-autoloader`
3. `npm ci && npm run build`
4. `php artisan migrate --force`
5. `config:cache && route:cache && view:cache`
6. Install supervisor conf + cron lines above; `supervisorctl reread/update`.
7. Smoke: `/up` → 200; login as admin → `/admin/metrics` shows counts.
