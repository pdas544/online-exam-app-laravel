#!/usr/bin/env bash
# Install (or refresh) the production web stack inside WSL2:
#   nginx :80  +  php-fpm pool [prod-exam-system] on 127.0.0.1:9002
# Usage: ./deploy/prod-wsl/install.sh [--dry-run]
# --dry-run renders to stdout without touching /etc (no sudo needed).
# Run this INSIDE the WSL2 Ubuntu distro (not Windows, not /mnt/c).
set -u

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
APP_PATH="$ROOT"
APP_USER="$(whoami)"
FPM_ADDR="127.0.0.1:9002"
NGINX_DST="/etc/nginx/sites-enabled/prod-exam-system"
FPM_DST="/etc/php/8.4/fpm/pool.d/prod-exam-system.conf"

render() {
    sed -e "s|{{APP_PATH}}|${APP_PATH}|g" \
        -e "s|{{FPM_ADDR}}|${FPM_ADDR}|g" \
        "$ROOT/deploy/prod-wsl/nginx.conf.template"
}

render_pool() {
    sed -e "s|{{USER}}|${APP_USER}|g" \
        "$ROOT/deploy/prod-wsl/php-fpm-pool.conf.template"
}

if [ "${1:-}" = "--dry-run" ]; then
    echo "### $NGINX_DST"
    render
    echo "### $FPM_DST"
    render_pool
    exit 0
fi

# Port 80 is promiscuous — refuse to fight whoever owns it.
if curl -sf -o /dev/null --max-time 3 http://localhost/ 2>/dev/null; then
    echo "WARNING: something already answers on :80." >&2
    echo "Stop that site first — only one nginx server may be default on :80." >&2
fi

render | sudo tee "$NGINX_DST" > /dev/null
render_pool | sudo tee "$FPM_DST" > /dev/null
# WSL2 may not run systemd — prefer it, fall back to service(8).
if [ -d /run/systemd/system ]; then
    sudo nginx -t && sudo systemctl reload-or-restart nginx php8.4-fpm
else
    sudo nginx -t && sudo service nginx reload && sudo service php8.4-fpm restart
fi

echo "Prod stack up: http://<server-lan-ip>/ (APP_URL must match exactly)."
echo "Then set APP_URL in .env and run: php artisan config:clear"
