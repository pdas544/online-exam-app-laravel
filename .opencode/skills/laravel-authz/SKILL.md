---
name: laravel-authz
description: Use when adding routes, controller actions, or views that need access control in this role-column Laravel app. Triggers on keywords: policy, authorize, abort 403, middleware, teacher, admin, student, role, gate.
---

# Authorization (role column + Policies)

## The model

- Roles live on `users.role` with `User::isAdmin()/isTeacher()/isStudent()`.
- **No Gates defined** — access is Policies + inline checks.
- `bootstrap/app.php` is the source of truth (Laravel 12).
  `app/Http/Kernel.php` is legacy and **ignored** — never register middleware
  there. New aliases must be registered in `bootstrap/app.php`.
- Only custom alias: `teacher` (allows teacher **OR** admin), used on
  `/teacher/monitor*` only.

## Rules for new endpoints

1. **Policy first.** If a model backs the route, add/extend its Policy
   (`app/Policies/`, registered via `Gate::policy()` in `AppServiceProvider`)
   and call `$this->authorize(...)` — with `AuthorizesRequests` on the
   controller. Never invent a middleware alias without registering it.
2. **Inline `abort(403)` only when no model exists** (dashboard scoping,
   aggregate pages). Follow the existing controller pattern.
3. **Students see only their own rows** — scope by `Auth::id()` even when a
   Policy passes (IDOR: any authenticated student can hit any `/sessions/{id}`
   URL; `view` Policy must owner-check).
4. **Teachers see only their exams/sessions** (`teacher_id` scoping);
   `forceEnd` requires ownership via Policy, not just the `teacher` middleware.
5. **Broadcast channels need auth.** Events currently use public `Channel`;
   until `routes/channels.php` authorizes `exam.{id}` / `teacher.{id}` /
   `student.{id}` (see `BaseExamEvent`), assume any listener can subscribe —
   don't put PII in payloads.

## Known traps

- Route name `dashboard` does not exist — use `admin/teacher/student.dashboard`.
- `GET questions/import` must be declared **before** `Route::resource(...)`
  or it gets shadowed.
