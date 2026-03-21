# Redis Migration Guide

Migrate the Online Exam System from `database` cache driver to `redis` cache driver.

---

## Current State

| Setting | Current Value |
|---|---|
| `CACHE_STORE` | `database` (cache table in PostgreSQL) |
| `QUEUE_CONNECTION` | `database` (jobs table in PostgreSQL) |
| `SESSION_DRIVER` | `database` (sessions table in PostgreSQL) |
| `REDIS_CLIENT` | `phpredis` (set but extension not installed) |
| Redis server | **Not installed** |
| phpredis PHP extension | **Not installed** |
| predis composer package | **Not installed** |

---

## Step-by-Step Migration

### Step 1 — Install Redis Server

```bash
sudo apt update
sudo apt install -y redis-server
```

Enable and start Redis to run on every boot:

```bash
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

Verify Redis is running:

```bash
redis-cli ping
# Expected: PONG

redis-cli --version
# Expected: redis-cli 7.x.x
```

---

### Step 2 — Install phpredis PHP Extension

```bash
sudo apt install -y php8.4-redis
```

Enable the extension and reload PHP:

```bash
sudo phpenmod redis
# If using PHP-FPM:
sudo systemctl restart php8.4-fpm
```

Verify the extension is loaded:

```bash
php -m | grep redis
# Expected: redis
````

> **Alternative**: Use `predis` (pure-PHP, no extension needed).
> ```bash
> composer require predis/predis
> ```
> Then set `REDIS_CLIENT=predis` in `.env`.
> phpredis is preferred for better performance; use predis if you cannot install OS packages.

---

### Step 3 — Configure Redis in `.env`

Change the cache store to `redis`:

```diff
-CACHE_STORE=database
+CACHE_STORE=redis
```

The following Redis settings are already present and correct — no changes needed:

```env
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

The `redis` cache store in `config/cache.php` uses the `cache` Redis connection defined in `config/database.php`, which points to **Redis DB 1** (`REDIS_CACHE_DB=1`). This separates cache data from other Redis usage (queues use DB 0 by default).

---

### Step 4 — Clear Existing Database Cache Entries

Flush all stale entries from the old `cache` database table:

```bash
php artisan cache:clear
```

Optionally truncate the old cache table directly (the table is no longer needed once Redis is active):

```sql
TRUNCATE TABLE cache;
TRUNCATE TABLE cache_locks;
```

---

### Step 5 — Verify Redis Cache is Working

```bash
php artisan tinker --execute='
    Cache::put("redis-test", "ok", 30);
    echo Cache::get("redis-test") . PHP_EOL;
    echo Cache::getStore()::class . PHP_EOL;
'
```

Expected output:
```
ok
Illuminate\Cache\RedisStore
```

Direct Redis check:

```bash
redis-cli -n 1 keys "*"
# Should list the newly written cache key with app prefix
```

---

### Step 6 — Verify Application Cache Calls Work

The following services use `Cache::remember()` and will automatically use Redis after the env change:

| Service | Cache Key | TTL |
|---|---|---|
| `App\Services\SubjectService` | `subjects.all` + `subject.{id}` | 3600 s (1 hr) |
| `App\Services\ExamService` | `exam.{id}.with-questions` | 600 s (10 min) |

Test subject caching:

```bash
php artisan tinker --execute='
    $svc = new \App\Services\SubjectService();
    $subjects = $svc->getAllSubjects();
    echo "subjects=" . $subjects->count() . PHP_EOL;
    // Second call should hit cache:
    $start = microtime(true);
    $svc->getAllSubjects();
    echo "cached ms=" . round((microtime(true) - $start) * 1000, 2) . PHP_EOL;
'
```

---

### Step 7 — (Optional) Migrate Queue to Redis

The queue is currently database-backed. To also move it to Redis:

**`.env`**:
```diff
-QUEUE_CONNECTION=database
+QUEUE_CONNECTION=redis
```

This uses the `default` Redis connection (DB 0). No further config changes are needed — Laravel ships with a `redis` queue connector pre-configured.

Run the queue worker after the change:

```bash
php artisan queue:listen --queue=default,violations
```

Verify by dispatching a test job:

```bash
php artisan tinker --execute='dispatch(function() { logger("Redis queue works"); });'
# Then tail logs: php artisan pail
```

---

### Step 8 — (Optional) Migrate Sessions to Redis

To also move sessions to Redis:

**`.env`**:
```diff
-SESSION_DRIVER=database
+SESSION_DRIVER=redis
SESSION_CONNECTION=default
```

Then clear existing sessions (all users will be logged out):

```bash
php artisan session:flush   # or: TRUNCATE TABLE sessions;
```

---

## Rollback

If Redis becomes unavailable after migration:

1. Revert `.env`: `CACHE_STORE=database`
2. Run `php artisan cache:clear` to drop any in-memory state
3. The `cache` database table still exists and will resume being used

For bulletproof availability during the transition, temporarily use the built-in `failover` store in `config/cache.php`:

```php
'failover' => [
    'driver' => 'failover',
    'stores' => ['redis', 'database'],
],
```

Then set `CACHE_STORE=failover`. This automatically falls back to the database if Redis is down.

---

## Summary Checklist

- [ ] `redis-server` installed and running (`redis-cli ping` → `PONG`)
- [ ] `php8.4-redis` extension installed (`php -m | grep redis`)
- [ ] `.env`: `CACHE_STORE=redis`
- [ ] `php artisan cache:clear` executed
- [ ] `Cache::getStore()` returns `Illuminate\Cache\RedisStore` in tinker
- [ ] `SubjectService` and `ExamService` cache hits confirmed
- [ ] (Optional) `QUEUE_CONNECTION=redis` for Redis-backed queues
- [ ] (Optional) `SESSION_DRIVER=redis` for Redis-backed sessions
