#!/usr/bin/env bash
# Install (or refresh) the production web stack on this Ubuntu host:
#   nginx :80 -> :443 (DOMAIN)  +  php-fpm pool [prod-exam-system] on 127.0.0.1:9002
# Usage: ./deploy/prod/install.sh --domain exams.example.com [--dry-run] [--skip-certbot]
# --dry-run renders to stdout without touching /etc (no sudo needed).
# --skip-certbot renders + installs nginx/fpm but does not run certbot
#   (use when terminating TLS elsewhere or installing certs manually).
set -u

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
APP_PATH="$ROOT"
APP_USER="$(whoami)"
FPM_ADDR="127.0.0.1:9002"
NGINX_DST="/etc/nginx/sites-enabled/prod-exam-system"
FPM_DST="/etc/php/8.4/fpm/pool.d/prod-exam-system.conf"
DOMAIN=""
DRY_RUN=""
SKIP_CERTBOT=""

for arg in "$@"; do
    case "$arg" in
        --domain=*) DOMAIN="${arg#--domain=}" ;;
        --domain) shift ;;
        --dry-run) DRY_RUN=1 ;;
        --skip-certbot) SKIP_CERTBOT=1 ;;
        --*) echo "Unknown flag: $arg" >&2; exit 1 ;;
        *) [ -z "$DOMAIN" ] && DOMAIN="$arg" ;;
    esac
done
# Support `--domain value` (space form).
if [ "${1:-}" = "--domain" ]; then
    DOMAIN="${2:-}"
fi

if [ -z "$DOMAIN" ]; then
    echo "Usage: $0 --domain exams.example.com [--dry-run] [--skip-certbot]" >&2
    exit 1
fi

render() {
    sed -e "s|{{APP_PATH}}|${APP_PATH}|g" \
        -e "s|{{FPM_ADDR}}|${FPM_ADDR}|g" \
        -e "s|{{DOMAIN}}|${DOMAIN}|g" \
        "$ROOT/deploy/prod/nginx.conf.template"
}

render_pool() {
    sed -e "s|{{USER}}|${APP_USER}|g" \
        "$ROOT/deploy/prod/php-fpm-pool.conf.template"
}

if [ -n "$DRY_RUN" ]; then
    echo "### $NGINX_DST"
    render
    echo "### $FPM_DST"
    render_pool
    exit 0
fi

# Port 80/443 are promiscuous — refuse to fight whoever owns them.
if curl -sf -o /dev/null --max-time 3 "http://localhost/" 2>/dev/null; then
    echo "WARNING: something already answers on :80." >&2
    echo "Stop that site first — only one nginx server may be default on :80." >&2
fi

# Pool owner must own the repo or php-fpm returns "File not found" on every route.
if [ ! -x "$APP_PATH/public/index.php" ]; then
    echo "WARNING: $APP_USER cannot read $APP_PATH/public/index.php — fix ownership first." >&2
fi

render | sudo tee "$NGINX_DST" > /dev/null
render_pool | sudo tee "$FPM_DST" > /dev/null

if [ -z "$SKIP_CERTBOT" ]; then
    sudo apt install -y certbot python3-certbot-nginx
    sudo certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "admin@${DOMAIN#*.}" || {
        echo "certbot failed — nginx template references cert paths that do not exist yet." >&2
        echo "Fix DNS (A record $DOMAIN -> this host) then re-run certbot manually." >&2
    }
fi

sudo nginx -t && sudo systemctl reload-or-restart nginx php8.4-fpm
sudo ufw allow 80,443/tcp 2>/dev/null || true
sudo ufw allow 8081/tcp 2>/dev/null || true

echo "Prod stack up: https://$DOMAIN/ (APP_URL must match exactly)."
echo "Then set APP_URL=https://$DOMAIN in .env and run: php artisan config:clear"
