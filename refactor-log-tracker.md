# Refactor Log Tracker

> One row per backlog item (see `codebase-summary-muse-spark-1-3.md` §4). Update `Status`, `Actual tokens`, and `Notes` after each task. Commit per task; never mix tasks in one commit.

## Status legend
`pending` → `in_progress` → `done` (verified: `php artisan test` green + routes/blades compile). `deferred` = consciously postponed, with reason.

## Backlog

| ID | Item | Status | Est. tokens | Actual tokens | Notes |
|---|---|---|---|---|---|
| R0 | P0 crashes (Grade, users.show, import route, ilike, channels.php, max_attempts, dashboard route) | done | 15–20k | ~35k | +2 bonus fixes (missing `exam.result`/`import` views). Verified: tests 2/2, blades compile |
| P0.8 | Missing `DB` import in `ExamController` (5× `DB::transaction` → fatal) — found in cross-model comparison | pending | ~2k | | One-line fix; fold into R4 or fix immediately |
| R1 | 14 Form Requests + controller wiring | done | 25–33k | \} ~60k combined | Private validators deleted; checkbox semantics preserved |
| R2 | 5 Policies + `authorize()` + delete Kernel/RoleMiddleware | done | 18–24k | / | 6-case smoke suite kept at `tests/Feature/R1R2SmokeTest.php` |
| R3 | Extract `ExamSessionService` + `GradingService` + `ViolationService`; server-side expiry. Absorbed from A: async grading/violation jobs, DTOs, custom exceptions | in_progress | 50–80k | | R3a+R3b+R3c done test-first (17 cases). Controller rewired (371→250 lines). Found+fixed Carbon-3 signed-diff timer bug (`timeRemaining()` grew over time). Submit now idempotent; error bodies no longer leak internals. Uncommitted on `agent-edits` |
| R4 | Extract `ExamManagementService`; safe uploads. Absorbed from A: `FileService` | pending | 30–45k | | Test-first on attach/detach/reorder/recalc |
| R5 | Extract `DashboardService` + ViewModels; fix N+1, cache availability. Absorbed from A: `Cache::remember` recipes, column selection, view composers | pending | 30–45k | | Heaviest read: 553-line controller |
| R6 | Private broadcast channels + Echo update | pending | 15–25k | | Needs 2-browser manual test (not tokenizable) |
| R7 | API decision: ship `api/v1` OR delete dead Sanctum surface | pending | 5k (delete) / 25–40k (build) | | DECISION NEEDED before starting |
| R8 | Feature+Unit suite + CI. Absorbed from A: 100-student submit test, `ab` smoke, KPI targets (p95 <200ms, <10 queries/req, >80% cache hit/coverage) | pending | 35–55k | | Grows as R3–R5 land; gate 70% on `app/Services` |
| R9 | Hardening: throttle, passwords, uploads, headers, error pages | pending | 20–35k | | |
| R10 | Ops: expiry schedule, queue, backup runbook, `.env.prod`, metrics | pending | 15–25k | | Mostly config/docs — cheap |
| R11 | Cleanup: Tailwind/Bootstrap, Breeze, stubs, Blade dupes, a11y | pending | 15–25k | | |
| | **Remaining total** | | **~220–375k** | | |

## Budget guidance
- **Must-do core (production integrity):** R3+R4+R5+R6+R8 ≈ 160–250k.
- **Deferrable without blocking prod:** R7 (if delete: 5k), R10, R11 ≈ 35–95k.
- **If budget is tight:** do R3 → R6 → R8-core → R4 → R5, defer R7/R9/R10/R11.
- **To save tokens:** primary-agent sequential (shared context). Subagents only for R3–R5 domain splits, at ~1.4–1.7× token cost for ~30–40% wall-clock saving.

## Git discipline (see 2026-09-05 note below)
- Never `git add -A` — working tree mixes refactor work with a prior uncommitted Teacher/Student-profile feature.
- `git add -p` on overlapping files; new files (`Requests/`, `Policies/`, `channels.php`, …) are safe to stage whole.
- Commit per backlog ID on completion.

## Log
- 2026-09-05: Adopted conflict-free `monitoring`-branch work (staged, uncommitted): `Services/{Exam,Subject}Service`, `Jobs/{GradeExamSession,LogExamViolation}`, `Builders/ExamSessionBuilder`, perf-indexes migration (already applied in dev DB), hardened `StudentAnswer::autoGrade`, `console.php` maintenance commands, `exam-taker.js` + monitoring/dashboard blades. Skipped per rule: 7 overlapping controllers + `routes/web.php` (conflict), bulk docs (optional). Verified: 8/8 tests, blades compile.
- 2026-09-05: R0+R1+R2 done. Found working tree already contained an uncommitted Teacher/Student-profile feature (models, migrations, factories, seeders, blades, `UserController` Teacher blocks, `User.php`, `config/app.php`, `README.md`) mixed with refactor edits in `UserController`, `routes/web.php`, etc. Left all pre-existing work untouched; no commits made.
