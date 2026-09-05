---
name: db-compat-pgsql-sqlite
description: Use when writing or reviewing any Eloquent query, migration, or raw SQL in a project that runs Postgres in prod and sqlite in tests. Triggers on keywords: ilike, like, raw, DB::raw, operator, postgres, sqlite, phpunit.xml.
---

# DB Compatibility (pgsql prod, sqlite tests)

`phpunit.xml` forces `DB_CONNECTION=sqlite :memory:` while prod runs `pgsql`.
Every query must work on **both**.

## Rules

1. **Never use `ilike`.** It is Postgres-only and fatals sqlite tests.
   Use `like` instead — sqlite `like` is case-insensitive for ASCII by default,
   which matches search-box behaviour closely enough.
   ```php
   // BAD — breaks sqlite
   ->where('name', 'ilike', "%{$q}%")
   // GOOD
   ->where('name', 'like', "%{$q}%")
   ```
2. **No Postgres-only operators/functions** in queries or migrations:
   `ilike`, `distinct on`, `returning`, `::cast` syntax, `jsonb` operators
   (`->`, `->>`, `?`), `now()` raw, `regexp_*`. Prefer Eloquent / schema-builder
   equivalents, or JSON-column helpers that both drivers support.
3. **Raw expressions need a compat check.** Any `DB::raw` / `orderByRaw` /
   `whereRaw` must be eyeballed for both grammars. If driver-specific SQL is
   unavoidable, branch on `DB::getDriverName()` and cover both paths with tests.
4. **Migrations run on both.** `php artisan migrate --database=sqlite` in CI-style
   checks; avoid `CONCURRENTLY`, partial-index predicates, or custom enum types
   unless guarded.

## Verification

- `php artisan config:clear && php artisan test` — must stay green on sqlite.
- When touching search/sort/filter code, add a Feature test that exercises the
  query path (the bug class this skill prevents is "works on prod, red in CI").
