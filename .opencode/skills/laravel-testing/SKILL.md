---
name: laravel-testing
description: Use when adding or changing behaviour in Laravel — write the failing test first, then implement. Triggers on keywords: phpunit, feature test, unit test, --filter, factories, refreshdatabase, test-first, TDD.
---

# Laravel Testing (test-first)

## Commands

- Full suite: `composer run test` (`config:clear` + `php artisan test`).
- Single test: `php artisan test --filter=TestName` or
  `php artisan test tests/Feature/ExampleTest.php`.
- Style gate (no CI — run manually): `./vendor/bin/pint --test`; fix only files
  you touched.

## Conventions

1. **Test-first from service layer onward.** New business logic lands in
   `app/Services/` with a `tests/Unit/*ServiceTest.php` written before the
   implementation. Controller rewiring is verified by the existing suite, not
   by new controller tests.
2. **Unit tests use sqlite `:memory:`** (forced by `phpunit.xml`). Respect the
   `db-compat-pgsql-sqlite` skill — no Postgres-only SQL.
3. **Name tests by behaviour**, not by method:
   `submit_is_idempotent`, `start_rejects_fourth_attempt`, not `testSubmit`.
4. **Date/time edge cases are load-bearing.** Carbon 3 returns **signed**
   diffs: `diffInSeconds($date)` goes negative when `$date` is in the past.
   Always pass absolute mode explicitly — `diffInSeconds($date, true)` — or use
   `$model->timeRemaining()`-style helpers. Timer/expiry logic needs a test
   that advances time past the deadline (a past-deadline test once caught a
   timer that *grew* instead of shrinking).
5. **Keep the suite fast.** Prefer Unit tests for rules; use Feature tests for
   HTTP/policy wiring. Full suite should stay under a few seconds.

## Checklist before calling anything done

- [ ] New/changed behaviour has a failing-first test
- [ ] Full suite green (`25 passed` style output, zero skipped-silently)
- [ ] `pint --test` clean on touched files
