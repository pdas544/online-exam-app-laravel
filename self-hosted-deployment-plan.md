# Self-hosted deployment plan — online-exam-system

> Standalone guide: this same Ubuntu box goes from `git clone` to a public
> exam host. No other doc is required; related docs are linked, not assumed.
> Target verified on: Ubuntu 25.10, PHP 8.4, Node 20, 16 CPU / 26 GB RAM,
> with `nginx`, `redis-server`, `psql` already present and `supervisor`
> missing (step 1 installs it).

## Architecture (what you are building)

Single host, four long-lived processes (all four, or realtime silently degrades):

| Process | Command (prod) | Port |
|---|---|---|
| Web | nginx → php-fpm pool `[prod-exam-system]` on `127.0.0.1:9002`, serving `public/` | `:80` → `:443` |
| Queue ×4 | `deploy/supervisor.exam-system.conf` — `default×2`, `violations×1`, `grading×2 --backoff=10`, `broadcasts×1` (`queue:work redis --tries=3 --max-time=3600`) | — |
| Reverb | `php artisan reverb:start --port=8081` (via supervisor) | `:8081` (browsers dial `wss://DOMAIN:8081` directly, not proxied) |
| Scheduler | cron `* * * * *` → `schedule:run` (drives `exams:expire-sessions` every minute) | — |

Data: pgsql `exam_system` (truth) + self-hosted Redis (queues in DB 0, cache in DB 1, `appendonly yes`, `maxmemory-policy noeviction`). Sessions stay in `database`; cache `failover` falls back to database when Redis is down.

Golden invariants (violating any of these reproduces a past outage):

- `REVERB_PORT=8081`, never `:8080` — the web stack owns `:8080`.
- `REDIS_READ_TIMEOUT=60` must stay above `REDIS_QUEUE_BLOCK_FOR=5`.
- `QUEUE_CONNECTION` in `.env` must equal the driver the workers listen on (`redis`).
- `VITE_REVERB_*` bakes into the JS bundle at build time — rebuild after any change.

## 0. DNS first

Point an A record at this box (`DOMAIN`, e.g. `exams.example.com`) and wait for
propagation (`dig +short DOMAIN`). `APP_URL` and `VITE_REVERB_HOST` bake into
config/builds, so the name must be final before step 4. Certbot (step 5) also
needs port 80 reachable on that name.

## 1. OS packages

```bash
sudo apt update && sudo apt install -y nginx php8.4-fpm php8.4-pgsql \
  php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip \
  redis-server postgresql postgresql-contrib supervisor cron curl \
  composer nodejs npm certbot python3-certbot-nginx
```

Verify:

```bash
php -v            # 8.4.x
php -m | grep -E 'pgsql|mbstring|redis'
node --version    # v20.x
```

Notes: `predis/predis` (in `composer.json`) is pure PHP, so set
`REDIS_CLIENT=predis` and skip `php8.4-redis`. `supervisorctl` was absent on
this box — the install above fixes that. Enable unattended upgrades for
security patches: `sudo apt install -y unattended-upgrades`.

Firewall — web + Reverb public, DB/Redis loopback-only:

```bash
sudo ufw allow 80,443/tcp
sudo ufw allow 8081/tcp
sudo ufw --force enable
```

`redis.conf` and `postgresql.conf` stay bound to `127.0.0.1` (defaults); never
expose 5432/6379.

## 2. Postgres + Redis

```bash
sudo -u postgres createuser -P exam_app
sudo -u postgres createdb --owner=exam_app exam_system
```

(`pg_hba.conf` stays `scram-sha-256`; no remote listen change.)

`/etc/redis/redis.conf` (restart with `sudo systemctl restart redis-server`):

```
maxmemory 256mb
maxmemory-policy noeviction
appendonly yes
```

DB split is queue = DB 0, cache = DB 1 (`REDIS_DB=0`, `REDIS_CACHE_DB=1` per
`config/database.php` — the `docs/redis-plan.md` prose inverts them; trust the
config). Migrating existing data instead of a fresh DB: `pg_dump -Fc` on the
old host, then `pg_restore --clean -d exam_system` here followed by
`php artisan migrate --force`.

## 3. App install

```bash
cd /path/to/online-exam-system
composer install --no-dev --optimize-autoloader
cp .env.production.example .env
php artisan key:generate
```

Set in `.env` (`.env.production.example` has the base; add the starred rows —
they exist in `.env.example` but are missing from the production template):

| Key | Value |
|---|---|
| `APP_URL` | `https://DOMAIN` (must match what browsers type, exactly) |
| `APP_DEBUG` | `false` |
| `SESSION_SECURE_COOKIE` | `true` |
| `BROADCAST_CONNECTION` | `reverb` |
| `REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME` | `DOMAIN` / `8081` / `https` (host is the public name, not `127.0.0.1` — browsers dial it) |
| `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` | generate (`REVERB_APP_KEY` = random 32 chars works) |
| `REVERB_ALLOWED_ORIGINS` * | `https://DOMAIN` (default `*` in `config/reverb.php` is dev-only) |
| `VITE_REVERB_HOST` / `VITE_REVERB_PORT` / `VITE_REVERB_SCHEME` / `VITE_REVERB_APP_KEY` | same four values (baked into JS at build) |
| `QUEUE_CONNECTION` | `redis` |
| `CACHE_STORE` | `failover` (redis with database fallback — not bare `redis`) |
| `REDIS_CLIENT` / `REDIS_DB` / `REDIS_CACHE_DB` * | `predis` / `0` / `1` |
| `REDIS_TIMEOUT` * / `REDIS_READ_TIMEOUT` * / `REDIS_QUEUE_BLOCK_FOR` * | `5` / `60` / `5` (read timeout above block-for — always) |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | `exam_system` / `exam_app` / secret from step 2 |
| `AUTH_RATE_LIMIT` / `EXAM_RATE_LIMIT` / `EXAM_ANSWERS_RATE_LIMIT` * | `10` / `120` / `60` |
| `BACKUP_DIR` | `/var/backups/exam-system` |

Then:

```bash
php artisan migrate --force
npm ci && npm run build   # AFTER the VITE_* values above are final
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

After any later `.env` edit: `php artisan config:clear` (or re-run the cache
trio); after any `VITE_*` or `APP_URL` change: `npm run build` again.

## 4. Web stack (nginx + php-fpm)

New in this change: `deploy/prod/` templates (modeled on `deploy/local/` and
`deploy/prod-wsl/`; pool `:9002` collides with neither dev `:9001` nor
load-test `:9000`):

- `deploy/prod/nginx.conf.template` — `:80` → `:443` redirect + `443 ssl`
  (certbot paths), `client_max_body_size 12M`, hardening headers.
- `deploy/prod/php-fpm-pool.conf.template` — `[prod-exam-system]`,
  `pm=static max_children=30`, opcache on with timestamp validation.
- `deploy/prod/install.sh` — renders both with `--domain DOMAIN`
  (`--dry-run` previews, `--skip-certbot` for external TLS).

```bash
./deploy/prod/install.sh --domain DOMAIN --dry-run   # inspect (no sudo)
./deploy/prod/install.sh --domain DOMAIN             # installs + certbot + ufw
```

Cert renewal is automatic via the certbot timer
(`systemctl status certbot.timer`); nginx reloads pick up renewed certs.

## 5. Queues + Reverb + scheduler

```bash
sudo cp deploy/supervisor.exam-system.conf /etc/supervisor/conf.d/exam-system.conf
# Replace APP_PATH in that file with the repo path, then:
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status   # 6 programs RUNNING
```

Cron (`crontab -e`) — the scheduler expires timed-out sessions; the second
line takes the nightly backup:

```cron
* * * * * cd /path/to/online-exam-system && php artisan schedule:run >> /dev/null 2>&1
0 2 * * * /path/to/online-exam-system/scripts/backup.sh >> /path/to/online-exam-system/storage/logs/backup.log 2>&1
```

Dev-vs-prod trap: `composer run dev` runs a single `queue:listen --tries=1`
for convenience; prod runs the four per-queue `queue:work --tries=3` programs
above. Never substitute one for the other.

## 6. Backups, logs, updates, rollback

- Backup: `scripts/backup.sh` writes `pg_dump -Fc` into `$BACKUP_DIR`
  (`/var/backups/exam-system`), 14-day prune. Restore:
  `pg_restore --clean -d exam_system <file>` + `php artisan migrate --force`.
  Offsite sync of `$BACKUP_DIR` is still an open TODO (host-local only) —
  copy it somewhere loss of this box survives.
- Logs: supervisor writes `storage/logs/queue-*.log` + `reverb.log`
  unbounded — add logrotate (or `stdout_logfile_maxbytes`) and switch
  `LOG_STACK` from `single` to `daily` for Laravel logs.
- Updates: OS via `unattended-upgrades`; app updates behind
  `php artisan down`, then `git pull`, `composer install --no-dev`,
  `npm ci && npm run build`, `migrate --force`, re-cache, `php artisan up`.
- Rollback: `git <prior-SHA>`, restore prior `public/build`, down-migrate if
  the deploy added migrations, `config:clear` + re-cache, restart fpm +
  supervisor programs. No zero-downtime in v1 — schedule deploys outside exam
  windows.

## 7. Go-live gate (do all three)

1. Smoke: `https://DOMAIN/up` → 200; admin login → `/admin/metrics` shows
   counts, `queue_depth` 0, `failed_jobs` 0.
2. Realtime: 2-browser check — student lobby Proceed unlocks, teacher monitor
   goes live, grading toast arrives (catches `log`-driver or stale-bundle
   regressions).
3. Load: `./scripts/load-test.sh 100` on the fpm stack (never `php artisan
   serve`): submit-ack p95 < 2s, grading drain < 2 min, zero duplicate active
   sessions.

## Troubleshooting (seen before)

| Symptom | Cause → fix |
|---|---|
| Every route 404s "File not found" | Pool `user =` can't traverse to `public/` → pool owner must own the repo path |
| Logins loop back to login | `SESSION_SECURE_COOKIE=true` over HTTP, or `APP_URL` ≠ typed URL |
| Lobby Proceed never unlocks / monitor stale | Queue down or driver mismatch (worker driver ≠ `QUEUE_CONNECTION`); check `supervisorctl status`, redis queues |
| Live frozen, no console errors | Reverb down (`:8081` unreachable — ufw?) or stale Vite bundle → `npm run build` + hard refresh |
| Sessions stuck `in_progress` past time | Scheduler cron not running (`schedule:list`, cron service) |
| Blank page after `.env` edit | `php artisan config:clear` (or re-cache trio) |

Related docs: `docs/runbook.md` (ops + failure stories), `docs/redis-plan.md`
(queue rationale), `docs/windows-wsl-deploy.md` (LAN-only WSL2 variant),
`docs/runbook-loadtest.md` (harness guide).
