#!/usr/bin/env bash
# Load-test runner: swaps .env for a generated load merge (php-fpm only reads
# the .env FILE per request and clears its environment, so exports can't reach
# it), runs the worker + harness, then restores .env. No secrets are committed.
set -u

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BACKUP="$ROOT/.env.before-loadtest"
WORKER_LOG="$ROOT/.superpowers/sdd/2026-09-08-exam-load-test/worker.log"

restore() {
    if [ -f "$BACKUP" ]; then
        cp "$BACKUP" "$ROOT/.env"
        rm -f "$BACKUP"
    fi
    if [ -n "${SUPERVISOR_PID:-}" ]; then
        kill "$SUPERVISOR_PID" 2>/dev/null || true
    fi
    pkill -f "queue:work --sleep=1 --tries=1" 2>/dev/null || true
}
trap restore EXIT INT TERM

# Never compete with a stale wrapper worker (e.g. after a killed run).
pkill -f "queue:work --sleep=1 --tries=1" 2>/dev/null || true

if [ -f "$BACKUP" ]; then
    echo "Refusing: $BACKUP exists (a previous run did not restore). Inspect and remove it, then retry." >&2
    exit 2
fi

if ! curl -sf -o /dev/null http://localhost:8080/up; then
    echo "nginx+php-fpm not answering on :8080 — see docs/runbook-loadtest.md Prerequisites." >&2
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
# fresh ids — start from an empty queue.
php artisan queue:clear redis --queue=default > /dev/null 2>&1 || true

export APP_KEY="$APP_KEY"  # CLI safety net; fpm workers read the file above
# Supervised worker: restarts on crash (e.g. a migrate:fresh drop window
# under a live worker). Pattern-scoped so dev workers are never touched.
(
    while true; do
        php artisan queue:work --sleep=1 --tries=1 >> "$WORKER_LOG" 2>&1
        sleep 2
    done
) &
SUPERVISOR_PID=$!
sleep 5

php artisan exams:load-test "$@"
STATUS=$?
exit "$STATUS"
