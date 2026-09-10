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
- **Scheduler down**: timed-out sessions sit `in_progress` forever. Check
  cron; `schedule:list` shows registered commands.

## Backup & restore

- Nightly: `scripts/backup.sh` via cron (`0 2 * * *`). 14-day retention,
  `pg_dump` custom format into `$BACKUP_DIR`.
- Restore: `pg_restore -h … -U … -d exam_system --clean <file>`, then
  `php artisan migrate --force` to confirm schema currency.
- TODO (open): offsite sync of `$BACKUP_DIR` — currently host-local only.

## Deploy checklist

1. Copy `.env.production.example` → `.env`, fill secrets, `key:generate`
   (fresh installs only).
2. `composer install --no-dev --optimize-autoloader`
3. `npm ci && npm run build`
4. `php artisan migrate --force`
5. `config:cache && route:cache && view:cache`
6. Install supervisor conf + cron lines above; `supervisorctl reread/update`.
7. Smoke: `/up` → 200; login as admin → `/admin/metrics` shows counts.
