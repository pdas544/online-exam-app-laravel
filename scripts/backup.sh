#!/usr/bin/env bash
# Nightly pgsql backup with 14-day retention. Run from cron:
#   0 2 * * * APP_PATH/scripts/backup.sh >> APP_PATH/storage/logs/backup.log 2>&1
# Required env (or .env.production): DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD BACKUP_DIR
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/var/backups/exam-system}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M%S)"

mkdir -p "$BACKUP_DIR"
export PGPASSWORD="$DB_PASSWORD"

pg_dump -h "${DB_HOST:-127.0.0.1}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME}" \
  -F c -f "$BACKUP_DIR/exam_system-$STAMP.dump" "${DB_DATABASE:-exam_system}"

# Retention prune
find "$BACKUP_DIR" -name 'exam_system-*.dump' -mtime +"$RETENTION_DAYS" -delete

echo "backup ok: exam_system-$STAMP.dump"
echo 'NOTE: offsite copy is a documented TODO (R10) — sync $BACKUP_DIR off-host.'
