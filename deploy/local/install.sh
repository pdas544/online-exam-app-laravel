#!/usr/bin/env bash
# Install (or refresh) the local prod-like web stack:
#   nginx :8080  +  php-fpm pool [local-exam-system] on 127.0.0.1:9001
# Usage: ./deploy/local/install.sh [--dry-run]
# --dry-run renders to stdout without touching /etc (no sudo needed).
set -u

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
APP_PATH="$ROOT"
APP_USER="$(whoami)"
FPM_ADDR="127.0.0.1:9001"
NGINX_DST="/etc/nginx/sites-enabled/local-exam-system"
FPM_DST="/etc/php/8.4/fpm/pool.d/local-exam-system.conf"

render() {
    sed -e "s|{{APP_PATH}}|${APP_PATH}|g" \
        -e "s|{{FPM_ADDR}}|${FPM_ADDR}|g" \
        "$ROOT/deploy/local/nginx.conf.template"
}

render_pool() {
    sed -e "s|{{USER}}|${APP_USER}|g" \
        "$ROOT/deploy/local/php-fpm-pool.conf.template"
}

if [ "${1:-}" = "--dry-run" ]; then
    echo "### $NGINX_DST"
    render
    echo "### $FPM_DST"
    render_pool
    exit 0
fi

# Refuse to fight the other :8080 owners; the ports are mutually exclusive.
if curl -sf -o /dev/null --max-time 3 http://localhost:8080/login 2>/dev/null; then
    echo "WARNING: something already serves Laravel on :8080 (load-test nginx?)." >&2
    echo "Stop that stack first — the two nginx sites share the port." >&2
fi

render | sudo tee "$NGINX_DST" > /dev/null
render_pool | sudo tee "$FPM_DST" > /dev/null
sudo nginx -t && sudo systemctl reload nginx php8.4-fpm

echo "Local stack up: http://localhost:8080 (APP_URL must match)."
echo "Then set APP_URL=http://localhost:8080 in .env and run: php artisan config:clear"
