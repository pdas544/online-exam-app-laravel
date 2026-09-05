# Codebase Summary — muse-spark-1-3

> Generated 2026-09-05 by inspecting source code directly. All existing `*.md` files were ignored (not read as source). Re-audited for production-readiness against industry best practices (Laravel 12 conventions, SOLID, OWASP, 12-factor).
> Refreshed post R0–R2 (see `refactor-log-tracker.md`, the living document): `Requests/`, `Policies/`, `channels.php` now exist; P0.1–P0.7 fixed. Open items are marked `[OPEN]`; absorbed findings from cross-model comparison are marked `[ADDED].

## 1. Overview

**What it is:** Role-based Online Exam System (admin / teacher / student) with live proctoring and realtime monitoring.

**Stack:**
- Backend: Laravel 12 (PHP ^8.2), `laravel/reverb ^1.7`, `laravel/sanctum ^4.3`, `laravel/tinker`, `laravel/breeze ^2.3` (installed, unused), `laravel/sail`, `pint`, PHPUnit 11
- Frontend: Blade (44 views) + Bootstrap 5.3.8 + Bootstrap Icons CDN, `laravel-echo + pusher-js`, `axios`, `sortablejs`, Vite 7 (`app.js`, `exam-taker.js` ~917 lines, `teacher-monitor.js` ~120 lines, `exams/exam-questions.js` ~490 lines)
- DB: `config/database.php` default `sqlite`; `.env.example` shows `pgsql exam_system`; session/cache/queue default `database`
- Realtime: Reverb + Echo; Queue: `database` + `queue:listen` in `composer dev`
- Routes: `routes/web.php` (session-auth only), `routes/console.php` (only `inspire`), `routes/channels.php` (added R0: `exam.{id}`, `student.{id}`, `teacher.{id}`), no `routes/api.php`

**Layout (`app/` PHP files):**
- `Models/` (9): `User, Teacher, Student, Subject, Question, Exam, ExamSession, StudentAnswer, ViolationLog`
- `Http/Controllers/` (13): `Auth, Home, Subject, Question, Exam, ExamSession, Admin/Dashboard, Admin/User, Dashboard/{Base,Admin,Teacher,Student}, Teacher/LiveMonitoring`
- `Http/Requests/` (14, added R1): `Auth/{Login,Register,VerifyRole}`, `StoreSubject/Question/Exam/User`, `AddExamQuestion`, `ReorderQuestions`, `UpdateQuestionPoints`, `BulkAddQuestions`, `SaveAnswer`, `LogViolation`, `SendWarning`
- `Policies/` (5, added R2, registered in `AppServiceProvider`): `Exam, Question, Subject, ExamSession (view/forceEnd), User (admin-only)`
- `Http/Middleware/`: `TeacherMiddleware` only (legacy `Kernel.php` + unused `RoleMiddleware` deleted R2; `bootstrap/app.php` is the source of truth)
- `Events/` (11 broadcast events, 9 functional + 2 stubs)
- `Traits/BreadcrumbTrait`, `Providers/AppServiceProvider` (`Paginator::useBootstrapFive()` + `Gate::policy` mappings)
- Still missing: `app/Services/`, `app/Jobs/`, `app/Http/Resources/`, `app/Console/`, `app/Listeners/`
- `database/migrations/` 14 tables (FKs + some composite indexes present — see §3.4), `seeders/` + `factories/` for all core models
- `tests/`: `Feature/ExampleTest`, `Unit/ExampleTest` + `Feature/R1R2SmokeTest` (6 authZ/validation cases, added R2; full suite is R8)

## 2. What Works

- **Auth + RBAC (functional):** custom `AuthController@login/register/logout` (now on `LoginRequest/RegisterRequest`) with `Auth::attempt` + session regenerate/invalidate; `User` helpers `isAdmin/isTeacher/isStudent/hasRole`; per-role dashboards + layouts; `teacher` middleware on `/teacher/monitor*`; resource authorization via `Policies/` + `$this->authorize()` (R2; base `Controller` uses `AuthorizesRequests`). Seeder ships `admin@examsystem.com`.
- **Content management:** Subject CRUD (teacher-scoped + `SoftDeletes`); Question CRUD for 4 types (`mcq_single/mcq_multiple/true_false/fill_blank`, JSON casts, `duplicate(replicate+[COPY])`); Exam CRUD + question attach/detach/reorder (Sortable)/points-override/bulk + `updateTotalMarks()` in transactions; delete blocked when sessions `completed/in_progress`.
- **Exam delivery + grading:** `ExamSessionController::start/take/resume/saveAnswer/submit/result` + `StudentAnswer::autoGrade()` for all 4 types + score%/passed + `ExamEnded` broadcast. `exam-taker.js` handles pagination, lobby/pause modals, timer polling, violation detection (tab/window/fullscreen/copy/paste/resize).
- **Proctoring:** `ExamSession::logViolation(severity 1-5, auto-terminate ≥5)`, `ViolationLog` table (8 enum types + metadata JSON), `LiveMonitoringController::{index,monitor,getSessions,startExam,sendWarning,resumeSession,showSession}` + `teacher-monitor.js` (Echo + 5s polling).
- **Data layer basics:** FK `constrained()->onDelete(cascade)` on all domain tables; composite indexes already on `exam_sessions(exam_id,student_id,status)`, `student_answers(exam_session_id,is_answered)`, `violation_logs(exam_session_id,violation_type)`; `phpunit.xml` sqlite `:memory:`; `composer setup/dev/test` scripts; Vite build artifacts present.

## 3. What Needs Improvement (production-readiness gaps)

### P0 — crashes / broken paths (R0 fixed P0.1–P0.7; P0.8 [OPEN, ADDED] found in cross-model comparison, verified)
1. [DONE] `ExamSession::grade()` fatal — relation removed; `result()` redirects to existing `student.results.show`.
2. [DONE] `users.show` — view created.
3. [DONE] `questions/import` shadowed — moved above resource; stub view created.
4. [DONE] `ilike` — replaced with `like` in both controllers.
5. [DONE] Realtime wiring — `routes/channels.php` created + `channels:` in `bootstrap/app.php` (`/broadcasting/auth` auto-registered, manual route removed); `REVERB_*`/`VITE_REVERB_*` keys added (commented) to `.env.example`. Default stays `log`; events still public `Channel` (private-channel migration is R6).
6. [DONE] `max_attempts` — `start()` counts `completed` sessions against the limit.
7. [DONE] `dashboard` route — role-aware `dashboardRoute()` helper.
8. [OPEN, ADDED] `ExamController` calls `DB::transaction()` 5× (`addQuestion/removeQuestion/reorderQuestions/updatePoints/bulkAddQuestions`, lines ~252–342) with **no `DB` facade import** — resolves to `App\Http\Controllers\DB` → fatal on every question-management write. One-line fix (`use Illuminate\Support\Facades\DB;`), fold into R4 or fix immediately.

### 3.1 Architecture & maintainability — services still missing; Requests/Policies done (R1/R2)
> This is the area that covers "controllers handling multiple logic / needs service layers / Form Requests missing / scattered validation", plus the authorization twin of the same problem.

**Fat controllers (evidence; authZ/validation since extracted, business logic remains):**
| Controller | Size | Logics still inside (service candidates) |
|---|---|---|
| `ExamController.php` | 11 actions | filtering/query-building + file upload/delete (`Storage`) + pivot `order_index` re-sequencing + `updateTotalMarks()` + stats `groupBy(question_type)` |
| `ExamSessionController.php` | 8 actions | session bootstrap + answer seeding + grading loop + score%/`passed` calc + `timeRemaining` calc (duplicates `ExamSession::timeRemaining()`) + violation pause policy + 3× broadcast fan-out + `DB::beginTransaction` + dead `calculateGrade()` |
| `Dashboard/StudentDashboardController.php` (553, largest) | 3 public + ~10 private | aggregations (`sum(points_earned)` in loops), availability policy (`getMaxAttemptsReachedExamIds`), date formatting, view shaping (`formatAnswerForDisplay/normalizeAnswerValues`) — all controller-private, untestable in isolation |
| `QuestionController.php` | 7 actions | `processQuestionData()` JSON-encoding + stub `import()` (validation moved to `StoreQuestionRequest`) |
| `LiveMonitoringController.php`, `Admin/UserController.php` | — | broadcast calls + Teacher-profile `updateOrCreate` |

**Done in R1/R2:** 14 Form Requests (all 15 former inline `$request->validate()` sites + private `validateExam()/validateQuestion()` deleted); 5 Policies with `Gate::policy` registration; ~30 inline `abort(403)` sites replaced by `$this->authorize()`; every Request `authorize()` delegates to its Policy; legacy `Kernel.php`/unused `RoleMiddleware` deleted.

**No service layer (still [OPEN]; verified `app/Services/` missing):** business rules live in controllers or leak into models (`ExamSession::logViolation` auto-terminates + writes `ViolationLog` + mutates session — mixes domain + persistence + policy). Nothing is injectable/mockable.

**[ADDED] Question–exam scoping gap (fold into R3/R4):** `addQuestion/updatePoints/reorderQuestions/removeQuestion/bulkAddQuestions` validate that IDs *exist* but not that the question belongs to the exam's subject or is attached to *that* exam — cross-exam pivot writes possible. Scope every mutation through the exam (`whereHas`/pivot-membership check) and require the reorder set to match the exam's question set.

**[ADDED] Submission integrity (fold into R3):** `submit()` is not idempotent (double-click/duplicate POST can re-grade/re-notify) and no explicit status-transition guard exists (`scheduled→in_progress→completed/terminated`). Add idempotent submit + server-side transition enforcement alongside the expiry job.

**Fix (industry standard — thin controllers, Laravel conventions):**
- `app/Services/`: `ExamSessionService{start,submit,forceEnd,expire}`, `GradingService{grade,score}`, `ViolationService{record,escalate}`, `ExamManagementService{attach,detach,reorder,recalc}`, `DashboardService{stats,availability}`, `MonitoringService{broadcast}`. Target controllers <150 lines: validate → service → redirect/json.
- Move presentational formatting (`formatAnswer*`, stats arrays) to ViewModels/DTOs or Blade components; move grading math to `GradingService` (unit-testable).

### 3.2 Security (OWASP)
- No `throttle` on `login/register/answer/violation/status` (`bootstrap/app.php` registers only `teacher` alias; `Kernel.php:throttle` alias unused). Add `throttle:login`, `throttle:api`-style limits; add `RateLimiter` for exam endpoints (anti-brute-force + anti-spam).
- Weak password rule (`min:8`, no complexity, no `Password::defaults()`); no email verification (`MustVerifyEmail` unused), no 2FA; `register` allows `teacher` self-registration (privilege-escalation by design — confirm intended).
- Mass assignment audited OK (`$fillable` everywhere) but `User::role` is fillable + set from request in `register` (allow-listed to student/teacher — OK) and `UserController` (admin-only — OK). Still prefer Form Requests + `validated()` only.
- XSS: Blade `{{ }}` escaped by default — verify `take.blade.php`/`exam-questions.js` preview modals don't use `{!! !!}` with question text; `instructions` (longText) rendered unescaped would be stored-XSS. Add output-encoding audit + `strip_tags`/purify on rich fields.
- CSRF OK (`csrf-token` meta + `axios` header); `/broadcasting/auth` now auto-registered via `channels:` in `bootstrap/app.php` with channel definitions in `routes/channels.php`. Events still public `Channel` — public-channel data leak (`violation.detected`/`exam.ended` visible to any subscriber) closes in R6 with `PrivateChannel`.
- File upload (`instructions_file pdf/doc/txt 5M`) stored on `public` disk — serve via signed URLs or non-public disk; add MIME + virus-scan guidance; no image/file size throttling per exam. **[ADDED] `destroy()` orphans the file** (verified: only `update()` deletes the old file) — add `Storage::delete` on destroy + retention policy (fold into R4).
- Missing security headers (no `SecureHeaders` middleware, no CSP for Echo/pusher); `SESSION_ENCRYPT=false`, `cookie same_site lax` OK but `secure` flag env-dependent — set `SESSION_SECURE_COOKIE=true` in prod; add `Referrer-Policy`, `X-Content-Type-Options` via middleware or web server.
- [DONE R2] `ExamController::__construct` null-auth abort removed; per-action `$this->authorize()` + Request-level `authorize()` throughout.

### 3.3 API / contracts
- Dead Sanctum surface (`HasApiTokens` + tokens migration + `profile()/verifyRole()` JSON) with no `api` guard in `config/auth.php`, no `routes/api.php`, no `Http/Resources`. Either ship versioned JSON API (`/api/v1`, resources, `auth:sanctum`, OpenAPI doc) or delete tokens table + API methods to reduce attack surface.

### 3.4 Data & DB
- Good: FKs + composite indexes exist on hot paths. Still missing: unique `(exam_id,student_id,status)` partial coverage for active sessions (race on double-`start` — add DB unique + `firstOrCreate`/advisory lock); index on `questions(subject_id,question_type)`, `exams(status,available_from,available_to)`, `exam_questions(exam_id,order_index)`; `students(roll_number)` unique present — confirm case-insensitive collation intent.
- Soft deletes on Subject/Question/Exam but not on sessions/answers — confirm retention policy; detached pivot rows on force-delete need explicit cleanup (currently `detach()` manual — OK but move to observer).
- No observers/factories for sessions; `Grade` ambiguity (§P0.1) blocks `result()` view (`resources/views/exam.result` vs `dashboard/student/results/show` duplication — pick one).
- Time/expiry integrity: `started_at nullable`, `status scheduled→in_progress` transition never persisted in `take()`; `remaining_time` column written never; client timer is authoritative. Add server-side `expire` job + `CHECK (submitted_at >= started_at)`.

### 3.5 Testing & QA
- Coverage ~0% + 6 R2 smoke cases (`R1R2SmokeTest`: student/teacher/admin RBAC on `/exams`+`/users`, register validation, cross-owner question 403). Still no Feature tests for `start→answer→submit→result`, `max_attempts`, `forceEnd`, violation auto-terminate, broadcast fakes (`Event::fake`), time-edge (expiry). Add `tests/Feature/{Auth,ExamFlow,Proctoring,Monitoring}Test` + `tests/Unit/{Grading,Violation,Availability}Test` with sqlite `:memory:` (already configured). Gate on coverage threshold (e.g. 70% `app/Services`).
- No static analysis (`phpstan/larastan`), no `pint --test` gate (fails repo-wide on pre-existing style — new R1/R2 files are clean), no dusk/browser test for `exam-taker.js`.

### 3.6 Observability & error handling
- Only 2 `\Log::error` (both in `submit()`); no structured logging, no request-ID, no exception reporting (Sentry/Flare), `withExceptions()` empty in `bootstrap/app.php`. Add `report()` context (session/exam/user), `Log::warning` for violations, audit trail for `forceEnd/warn/resume`.
- User-facing errors leak internals (`'Failed to start exam: '.$e->getMessage()`); replace with generic message + logged reference ID. Add 403/404/419/500 Blade pages (currently default).
- No health beyond `/up`; no metrics (exam starts/submits/violations latency); add Laravel Pulse or Prometheus endpoint for prod.

### 3.7 Performance & scalability
- Chatty polling: `status` per-student + 5s `getSessions` per teacher + `updateProgress()` per answer (`count()` + `pluck()` + `save()`). Add eager loads (`with(exam,answers.question)` already partial), cache `availableExams` per minute, paginate `getSessions`, debounce `saveAnswer`, index audit above. `DATABASE` queue + `log` broadcast won't scale — move to Redis + Reverb horizontal (sticky sessions) or Pusher; document `queue:work --tries` + supervisor.
- **[ADDED] Sync submit/grading risk:** `submit()` grades all answers in-request — 100 concurrent submits risk HTTP timeouts + write contention. Move grading to `Jobs/GradeExamSession` (R3) and violation logging to a `violations` queue; keep `DATABASE` queue only until Redis lands (R10).
- `manageQuestions()` runs two paginated queries + `whereNotIn(pluck)` — replace with `whereDoesntHave('exams')` subquery; `StudentDashboard::getAvailableExams` N+1 `questions()->count()` per exam — use `withCount`.

### 3.8 DevOps & 12-factor
- No CI (` .github/workflows/` missing), no Dockerfile/compose (Sail present but no `docker-compose.yml` committed?), [DONE R0] `REVERB_*/VITE_REVERB_*` keys added (commented) to `.env.example` — uncomment + set `BROADCAST_CONNECTION=reverb` to enable; `APP_KEY` empty by design but `setup` script doesn't handle pgsql creds. Add `ci.yml` (`composer install`, `pint --test`, `phpstan`, `npm run build`, `php artisan test`, `migrate --force`), `.env.production.example`, `php artisan config:cache` guidance.
- No backup/retention runbook (exam data is high-value); no `schedule()` for expiry/cleanup (`routes/console.php` only `inspire`); add `exams:expire-sessions`, `sessions:cleanup-drafts`, `telescope:prune` schedules + supervisor/cron doc.
- Secrets in `.env.example` (`DB_PASSWORD=Root@123`) — replace with placeholders; confirm `.env` gitignored (it is via `.gitignore`) and add `APP_DEBUG=false` enforcement check in prod.

### 3.9 Frontend & UX consistency
- Bootstrap 5 + dead Tailwind directives (`@source/@theme` remain, `@import` commented) + `tailwindcss` still installed — remove one stack. `app.css` imports `bootstrap.min.css` directly instead of via Vite/SCSS — move to npm import.
- Blade duplication: `admin/dashboard.blade.php` vs `dashboard/admin/index`, `exam.result` vs `dashboard/student/results/show` (R0 picked the latter — `result()` redirects there). [DONE R0] `users.show` created, `questions/import` reachable with stub view. Consolidate layouts around `dashboard/layouts/base`.
- **[ADDED] Route hygiene (fold into R11):** repeated `auth` groups in `routes/web.php`, leftover commented routes/imports, inconsistent prefixes — group by role/feature, drop dead comments, add route smoke tests.
- No form-request error UX standard (`@error` present in some forms, missing in others); add `old()` preservation everywhere + `withQueryString()` on all paginated filters (only some have it).
- A11y/i18n: no `lang` switching (`APP_LOCALE` only), timer not screen-reader announced, no `aria-live` on violation warnings; add.

### 3.10 Code quality / tooling
- `pint` installed, never enforced (fails repo-wide on pre-existing style; R1/R2 additions are clean); add `pint --test` + editorconfig (present) + `larastan` level 5+. [DONE R2] Deleted `Kernel.php`, unused `RoleMiddleware`. Remaining: stub events (`StudentLeft`, `TeacherForceAction`), empty seeders, `getDetailedResults()` dead route (`student.results.detailed` view missing), Breeze-or-custom-auth decision (both half-present).

## 4. Refactor backlog (prioritized, production-ready order)

| ID | Item | Why | Effort | Impact |
|---|---|---|---|---|
| R0 | Fix P0 crashes (§3 P0.1–P0.8) | 500s, broken realtime, wrong attempts, missing DB import | S | Critical |
| R1 | [DONE] 14 Form Requests, private validators deleted | Kills scattered validation; unblocks thin controllers | S | High |
| R2 | [DONE] 5 Policies + `authorize()` in controllers/Requests; `Kernel.php`/`RoleMiddleware` deleted | Consistent 403s, testable authz | S | High |
| R3 | Extract `ExamSessionService + GradingService + ViolationService`; server expiry + idempotent submit + status transitions + question–exam scoping. Absorbed from A: async design — `Jobs/GradeExamSession` (grading off request cycle; sync loop timeouts under concurrent submit) + `Jobs/LogExamViolation` on a separate `violations` queue + `ExamSubmitted/ExamGradingCompleted` events; DTO convention (`CreateExamDTO`, `LogViolationDTO`, …) at service boundaries; custom exceptions (`ExamNotAvailable`, `SessionNotFound`) | Core exam integrity; removes god controller | M | Critical |
| R4 | Extract `ExamManagementService`; fix `DB` import (P0.8), file-orphan on destroy, pivot-membership checks. Absorbed from A: `FileService` for centralized instruction-file handling; DTOs for create/update paths | Removes 418-line controller; safe uploads | M | High |
| R5 | Extract `DashboardService` + ViewModels from `Student/Teacher/Admin` dashboards; fix N+1 (`withCount`), cache availability. Absorbed from A: `Cache::remember` recipes (subjects/question-types 3600s, exam questions 600s, student stats 300s); column selection (`select(id,…)`) on hot queries; view composers for shared Blade data | Kills 553-line controller; perf | M | High |
| R6 | Private broadcast channels + `routes/channels.php` + `REVERB_*` env + frontend Echo update | Closes realtime data leak | S | High |
| R7 | Decide API: ship `api/v1` (Sanctum + Resources + OpenAPI) or delete dead surface | Reduces attack surface / enables mobile | M | Med |
| R8 | Feature+Unit suite (auth, exam flow, attempts, violations, broadcasts) + CI (`test`, `pint`, `phpstan`, `build`). Absorbed from A: concurrency/perf harness — 100-student simultaneous-submit test (<30s budget), `ab -n 100 -c 10` smoke, KPI targets (p95 <200ms, <10 queries/req, cache hit >80%, coverage >80% on `app/Services`) | Regression safety for all above | M | High |
| R9 | Hardening: `throttle`, `Password::defaults`, signed upload URLs, security headers, generic error pages, audit logging | OWASP baseline | S–M | High |
| R10 | Ops: `exams:expire-sessions` schedule + queue (Redis/database split: default + `violations` queues, supervisor) + backup runbook + `.env.prod` + `/up` + metrics | Deployable + operable | M | High |
| R11 | Cleanup: route hygiene, Blade-logic reduction, Tailwind-vs-Bootstrap, Breeze decision, stub events/seeders, Blade consolidation, a11y pass | Debt removal | S | Med |

**Suggested execution:** ~~R0 → R1+R2~~ DONE → R3 → R4+R5 → R6+R9 → R8 (add tests per refactor) → R7/R10/R11. Keep each PR <300 lines; add a test before each service extraction. Progress tracked in `refactor-log-tracker.md`.
