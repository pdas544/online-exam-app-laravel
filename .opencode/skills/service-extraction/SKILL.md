---
name: service-extraction
description: Use when a Laravel controller action contains business logic, transactions, grading, or state transitions. Move it to app/Services with unit tests; keep controllers to HTTP + auth + broadcasts. Triggers on keywords: fat controller, service, refactor, transaction, state machine, idempotent, DomainException.
---

# Service Extraction (controllers → services)

Target shape: controllers handle **HTTP + authorization + broadcasts only**.
All rules, transactions, and state changes live in `app/Services/`.

## Procedure (test-first, one service at a time)

1. Write `tests/Unit/<Name>ServiceTest.php` covering the happy path, the
   guards, and the edge cases — watch them fail.
2. Implement `app/Services/<Name>Service.php`. Inject sibling services via
   constructor promotion (`private GradingService $grading`).
3. Rewire the controller to delegate; delete the inline logic and any helpers
   it orphaned (duplicate calculators, dead grade mappers, unused imports).
4. Run full suite + `pint --test` on touched files.

## Service design rules

- **Return models, throw `DomainException` for expected failures**
  (already-submitted, attempts exhausted, unavailable). Controllers catch it
  and map to 400/back-with-error. Reserve generic `\Exception` for real bugs.
- **One transaction per use-case**, inside the service (`DB::transaction` or
  manual begin/commit) — never split a unit of work across controller + service.
- **Idempotency is a feature.** Retried submits (double-click, flaky network)
  must return success, not 400. Guard with state checks first
  (`completed` → return existing), transitions second.
- **Explicit transition guards.** Model `markInProgress/pause/resume/submit/
  forceEnd/expire` as methods that throw on illegal from-states; test each
  illegal jump.
- **Don't leak internals.** Log `$e->getMessage()` server-side; return generic
  "Failed… Please try again." to JSON callers.
- **No error-message changes without checking callers.** Response shapes
  (`terminated`, `redirect`, `warning`) are consumed by JS — grep the frontend
  before renaming keys.
