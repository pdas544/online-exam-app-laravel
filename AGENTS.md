# AGENTS.md

## Commands
- Setup: `composer run setup` (copies `.env`, migrates, `npm install && npm run build`). Dev: `composer run dev` (serve + `queue:listen --tries=1` + pail + Vite concurrently). Test: `composer run test` (`config:clear` + `php artisan test`).
- Single test: `php artisan test --filter=TestName` or `php artisan test tests/Feature/ExampleTest.php`.
- Format check: `./vendor/bin/pint --test` (no CI enforces it — run before committing).

## Database gotcha
- Prod expectation is `pgsql` (`.env.example`), but `phpunit.xml` forces `sqlite :memory:`. Any raw operator must work on **both**.
- `SubjectController@index` and `UserController@index` use `ilike` (Postgres-only) — breaks sqlite tests. Use `like` for new search queries.

## Auth / authorization
- Roles live on `users.role` with `User::isAdmin/isTeacher/isStudent()`. No Policies/Gates exist — access is manual `abort(403)` inside controllers plus `teacher` middleware (allows teacher OR admin) on `/teacher/monitor*` only.
- Follow the existing pattern for new endpoints (inline check in controller). Don't invent middleware aliases without registering them in `bootstrap/app.php` — `app/Http/Kernel.php` is legacy and ignored by Laravel 12.

## Architecture (what's missing on purpose)
- No `app/Services/`, `app/Http/Requests/`, `app/Policies/`, `app/Jobs/`, `app/Http/Resources/` dirs. New business logic currently goes in controllers/models; validation is inline `$request->validate()`.
- Domain shapes (keep exact): `question_type` in `mcq_single,mcq_multiple,true_false,fill_blank`; MCQ `options` = JSON map `{"A":"..."}`, `correct_answers` = JSON array. Call `Exam::updateTotalMarks()` after pivot order/points changes.

## Realtime is broken by default
- `.env.example` sets `BROADCAST_CONNECTION=log` and has **no** `REVERB_*`/`VITE_REVERB_*` keys; there is no `routes/channels.php` and events use public `Channel`. Echo (`resources/js/bootstrap.js`, `VITE_REVERB_*`) won't work until keys + channel auth are added. Don't assume broadcasts fire in dev/test.
- Channel convention when fixing: `exam.{id}`, `teacher.{id}`, `student.{id}` (see `BaseExamEvent`).

## Known broken paths (don't replicate, fix on touch)
- `ExamSession::grade()` references missing `Grade` model — fatal if eager-loaded (`result()` does `load('grade')`).
- `UserController@show` renders `users.show` which doesn't exist (only `index,create,edit` blades).
- `GET questions/import` is defined **after** `Route::resource('questions')` in `routes/web.php` so it's shadowed — custom routes must go before resources.
- Some redirects use route name `dashboard`, which doesn't exist (only `admin/teacher/student.dashboard`).

## Frontend
- Vite entries: `resources/js/app.js`, `exam-taker.js`, `teacher-monitor.js` (+ `resources/css/app.css`). Tailwind plugin is commented out in `vite.config.js` — UI is Bootstrap 5; don't add Tailwind classes expecting them to compile.
- `exam-taker.js` needs `.question-card[data-question-id]` + session AJAX routes; `exams/exam-questions.js` reads endpoints from `data-config` JSON on page root. Changing those routes/markup breaks the JS silently.

## Stale docs
- `README.md` says Pusher/`BROADCAST_DRIVER=pusher`; truth is Reverb + `BROADCAST_CONNECTION` (`config/reverb.php`). Trust config. Validated detail lives in `.github/copilot-instructions.md` (data flows, event/JS wiring) — read it for domain context.
