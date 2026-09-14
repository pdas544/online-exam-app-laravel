# Windows 10 + WSL2 LAN deployment — online-exam-system

> Serve the app to students over **LAN-only plain HTTP** from a Windows 10
> desktop. The whole Laravel stack runs inside WSL2 Ubuntu (nginx + php-fpm +
> supervisor + cron + redis + pgsql); Windows only forwards ports, runs the
> firewall, and boots the stack. php-fpm has no Windows build — do not attempt
> a native Windows PHP stack; it would invalidate every rehearsed number.

## 0. What you need

- Windows 10 version 2004+ (build 19041+), virtualization enabled in BIOS.
- Administrator access on the box.
- The desktop on LAN with a **stable IP** (see step 2 — do this before building assets).
- This repo cloned **inside the Linux fs** (`~/online-exam-system`), never under `/mnt/c`.

## 1. Install WSL2 + packages (PowerShell as Administrator)

```powershell
wsl --install -d Ubuntu
wsl --set-default-version 2
```

Reboot when asked, create the Ubuntu user, then inside Ubuntu:

```bash
sudo apt update && sudo apt install -y nginx php8.4-fpm php8.4-pgsql php8.4-redis \
  redis-server postgresql postgresql-contrib supervisor cron curl \
  composer nodejs npm
```

## 2. Fix the server's LAN identity (before anything else)

`APP_URL` and the `VITE_REVERB_*` keys bake into config/builds, so the IP must
be stable **before** step 4:

1. Preferred: DHCP reservation for the desktop on the router.
2. Fallback: static IP on the box (Settings → Network → IPv4).
3. Record it: `SERVER_IP` (e.g. `192.168.1.50`). Students will use `http://<SERVER_IP>/`.

## 3. App install (inside Ubuntu, `~/online-exam-system`)

```bash
composer install --no-dev --optimize-autoloader
sudo -u postgres createuser --createdb exam_app
sudo -u postgres createdb --owner=exam_app exam_system
```

## 4. `.env` for LAN-HTTP (copy `.env.production.example`, then set)

| Key | Value | Why |
|---|---|---|
| `APP_URL` | `http://<SERVER_IP>` | Must match what browsers type, exactly |
| `APP_DEBUG` | `false` | Never true in prod |
| `APP_KEY` | (generate) | `php artisan key:generate` |
| `DB_DATABASE/USERNAME/PASSWORD` | `exam_system` / `exam_app` / secret | Local pgsql role from step 3 |
| `SESSION_SECURE_COOKIE` | `false` | **Critical:** `true` silently kills sessions over plain HTTP |
| `BROADCAST_CONNECTION` | `reverb` | Live lobby/monitor updates |
| `REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME` | `<SERVER_IP>` / `8081` / `http` | Browsers dial `ws://<SERVER_IP>:8081` directly |
| `VITE_REVERB_HOST/PORT/SCHEME` | Same three values | Baked into the JS bundle at build |
| `QUEUE_CONNECTION` | `redis` | Must equal the driver the workers listen on |
| `AUTH_RATE_LIMIT` / `EXAM_RATE_LIMIT` | `10` / `120` | Prod values from the template (not suite-safe defaults) |

Then:

```bash
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
npm ci && npm run build   # AFTER the VITE_* values above are final
```

> Rebuilding after any `VITE_*` or `APP_URL` change is mandatory — stale
> bundles dial the old host. After changing `.env`, always `config:clear`
> (or re-run the cache commands).

## 5. Web + app services (inside Ubuntu)

```bash
./deploy/prod-wsl/install.sh --dry-run   # inspect first (no sudo needed)
./deploy/prod-wsl/install.sh             # nginx :80 + pool [prod-exam-system] :9002
sudo cp deploy/supervisor.exam-system.conf /etc/supervisor/conf.d/exam-system.conf
# Replace APP_PATH in that file with ~/online-exam-system, then:
sudo supervisorctl reread && sudo supervisorctl update
```

Cron (drives `exams:expire-sessions` every minute) — `crontab -e`:

```cron
* * * * * cd ~/online-exam-system && php artisan schedule:run >> /dev/null 2>&1
0 2 * * * ~/online-exam-system/scripts/backup.sh >> ~/online-exam-system/storage/logs/backup.log 2>&1
```

Export the backup dir out of the VM nightly — append to the same crontab:

```cron
15 2 * * * cp ~/online-exam-system/backups/*.dump /mnt/d/exam-backups/ 2>/dev/null || true
```

(Adjust `/mnt/d/exam-backups/` to a second drive or USB stick. Same building,
off-host — the documented residual risk.)

Verify inside WSL: `curl -s -o /dev/null -w "%{http_code}\n" http://localhost/up`
→ `200`; log in as admin; `/admin/metrics` shows counts.

## 6. Windows glue (PowerShell as Administrator)

The WSL2 NAT address changes every reboot, so proxies go stale. The kit script
re-reads it and re-creates everything:

```powershell
cd C:\path\to\online-exam-system\deploy\prod-wsl
.\Update-PortProxy.ps1 -StartServices
```

It forwards `:80` + `:8081` → WSL, creates the two firewall rules (Exam HTTP,
Exam Reverb WS, Private/Domain), and boots nginx, php8.4-fpm, redis,
postgres, supervisor, cron. For `-StartServices` without a password prompt,
allow those six service commands passwordless sudo once (inside Ubuntu,
`sudo visudo`, append):

```
%sudo ALL=(ALL) NOPASSWD: /usr/sbin/service nginx *, /usr/sbin/service php8.4-fpm *, /usr/sbin/service redis-server *, /usr/sbin/service postgresql *, /usr/sbin/service supervisor *, /usr/sbin/service cron *
```

Boot persistence — Task Scheduler, "At startup", highest privileges:

```powershell
$Action = New-ScheduledTaskAction -Execute "powershell.exe" `
  -Argument "-ExecutionPolicy Bypass -File C:\path\to\online-exam-system\deploy\prod-wsl\Update-PortProxy.ps1 -StartServices"
$Trigger = New-ScheduledTaskTrigger -AtStartup
Register-ScheduledTask -TaskName "ExamStackBoot" -Action $Action -Trigger $Trigger `
  -User "SYSTEM" -RunLevel Highest
```

Power: set Windows to **never sleep**; before doors open, resync the clock
(`wsl --shutdown` from an elevated prompt, then re-run the proxy script —
WSL clocks drift after sleep and exam timing is server-side).

## 7. Exam-day gate + rehearsal

1. From a student machine: `http://<SERVER_IP>/up` → 200.
2. Load gate (needs the `:8080` rig free — it never touches prod ports, but run
   it during staging, not mid-exam): `./scripts/load-test.sh 100 --seed=100`
   must report 9/9 PASS, submit-ack p95 < 2s, drain < 2 min.
3. Mock exam from 3–5 real student machines: login → lobby → answers →
   violation pause → teacher resume → submit → results. Then `/admin/metrics`:
   `ungraded_completions` back to 0, `failed_jobs` 0.

## 8. Troubleshooting (seen before on other stacks)

| Symptom | Cause → fix |
|---|---|
| Every route 404s "File not found" | Pool user can't traverse to `public/` → check pool `user =` owns the repo path; same class of bug as the load-test rig's home-dir traverse |
| Works from the box, not from LAN | Stale WSL IP (re-run `Update-PortProxy.ps1`), firewall rule missing/scoped to Public, or desktop IP changed (see step 2) |
| Logins loop back to login page | `SESSION_SECURE_COOKIE=true` over HTTP, or `APP_URL` ≠ typed URL |
| Live lobby/monitor frozen, no console errors | Reverb down (`:8081` unreachable from student machine) or queue-driver mismatch (worker driver ≠ `QUEUE_CONNECTION`) |
| Sessions stuck `in_progress` past time | Scheduler cron not running inside WSL (`service cron status`) |
| Blank page after `.env` edit | `php artisan config:clear` (or re-cache); stale Vite bundle → `npm run build` |

## 9. Backout

Stop the Task Scheduler task, `netsh interface portproxy reset`, delete the
two firewall rules, `sudo rm /etc/nginx/sites-enabled/prod-exam-system
/etc/php/8.4/fpm/pool.d/prod-exam-system.conf` + reload. The Windows box is
otherwise untouched; dev laptops are unaffected.
