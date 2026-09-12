#!/usr/bin/env bash
# Load-test runner: swaps .env for a generated load merge (php-fpm only reads
# the .env FILE per request and clears its environment, so exports can't reach
# it), runs the worker + harness, then restores .env. No secrets are committed.
set -u

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BACKUP="$ROOT/.env.before-loadtest"
WORKER_LOG="$ROOT/.superpowers/sdd/2026-09-08-exam-load-test/worker.log"
# Must mirror the app's queue split (Task 3): a worker on default alone
# leaves grading/violations/broadcasts stranded while the drain check
# passes vacuously. Pattern doubles as the pkill scope below.
WORKER_QUEUES="grading,violations,broadcasts,default"
WORKER_PATTERN="queue:work --queue=${WORKER_QUEUES}"

restore() {
    if [ -f "$BACKUP" ]; then
        cp "$BACKUP" "$ROOT/.env"
        rm -f "$BACKUP"
    fi
    if [ -n "${SUPERVISOR_PID:-}" ]; then
        kill "$SUPERVISOR_PID" 2>/dev/null || true
    fi
    pkill -f "$WORKER_PATTERN" 2>/dev/null || true
}
trap restore EXIT INT TERM

# Never compete with a stale wrapper worker (e.g. after a killed run).
pkill -f "$WORKER_PATTERN" 2>/dev/null || true
# Legacy single-queue command from before the queue split — same reason.
pkill -f "queue:work --sleep=1 --tries=1" 2>/dev/null || true

if [ -f "$BACKUP" ]; then
    echo "Refusing: $BACKUP exists (a previous run did not restore). Inspect and remove it, then retry." >&2
    exit 2
fi

if ! curl -sf -o /dev/null http://localhost:8080/up; then
    echo "nginx+php-fpm not answering on :8080 — see docs/runbook-loadtest.md Prerequisites." >&2
    exit 2
fi

# /up alone is not proof of Laravel: a stray `reverb:start --port=8080`
# also answers it. Refuse unless a Laravel-only route responds (this exact
# false-positive once produced a fully invalid run: every bot 404'd on /login).
if [ "$(curl -s -o /dev/null -w '%{http_code}' http://localhost:8080/login)" != "200" ]; then
    echo "Refusing: :8080 is not serving Laravel (GET /login != 200)." >&2
    echo "Likely cause: a dev 'reverb:start --port=8080' is squatting on the" >&2
    echo "load-test port. Stop it (or move REVERB_PORT), then retry." >&2
    exit 2
fi

cp "$ROOT/.env" "$BACKUP"

APP_KEY=$(grep ^APP_KEY= "$BACKUP" | cut -d= -f2-)
DB_PASSWORD=$(grep ^DB_PASSWORD= "$BACKUP" | cut -d= -f2-)

# Strip keys we override, then append the load merge.
grep -vE "^(APP_URL|APP_DEBUG|DB_DATABASE|DB_USERNAME|DB_PASSWORD|QUEUE_CONNECTION|REDIS_HOST|REDIS_PORT|BROADCAST_CONNECTION|SESSION_DRIVER|CACHE_STORE|AUTH_RATE_LIMIT|EXAM_RATE_LIMIT)=" "$BACKUP" > "$ROOT/.env"
cat >> "$ROOT/.env" <<EOF
APP_URL=http://localhost:8080
APP_DEBUG=false
DB_DATABASE=exam_system_loadtest
DB_USERNAME=newuser
DB_PASSWORD=${DB_PASSWORD}
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
BROADCAST_CONNECTION=log
SESSION_DRIVER=database
CACHE_STORE=database
AUTH_RATE_LIMIT=100000
EXAM_RATE_LIMIT=100000
EOF

php artisan config:clear --ansi > /dev/null

# Tables must exist BEFORE the worker boots (it probes the cache table for
# restart signals and dies on missing relations).
php artisan migrate --force --ansi > /dev/null

# Stale jobs from a killed run would otherwise be graded against THIS run's
# fresh ids — start from empty queues (all four, not just default).
for q in grading violations broadcasts default; do
    php artisan queue:clear redis --queue="$q" > /dev/null 2>&1 || true
done

export APP_KEY="$APP_KEY"  # CLI safety net; fpm workers read the file above
# Supervised worker: restarts on crash (e.g. a migrate:fresh drop window
# under a live worker). Pattern-scoped so dev workers are never touched.
# The log dir is gitignored and may not exist — without it the redirect
# fails and the worker never starts (silent 400-job backlog).
mkdir -p "$(dirname "$WORKER_LOG")"
(
    while true; do
        php artisan queue:work --queue="$WORKER_QUEUES" --sleep=1 --tries=1 >> "$WORKER_LOG" 2>&1
        sleep 2
    done
) &
SUPERVISOR_PID=$!
sleep 5

php artisan exams:load-test "$@"
STATUS=$?
exit "$STATUS"
