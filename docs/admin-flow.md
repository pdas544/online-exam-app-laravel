# Admin Flow Architecture

This document describes the admin-side architecture. Admin behavior in this codebase is partly native and partly inherited from teacher/shared flows. That distinction matters for refactoring because the current admin experience is not isolated behind an admin-only service layer.

## Scope

- Actor: authenticated user with `role = admin`
- Admin responsibilities implemented today:
- view admin dashboard
- manage users directly
- manage subjects, questions, and exams through shared controllers
- participate in teacher monitoring flows because admin is explicitly allowed there

## Primary Entry Points

- Admin dashboard route in [routes/web.php](routes/web.php)
- Dashboard controller used by the route: [app/Http/Controllers/Dashboard/AdminDashboardController.php](app/Http/Controllers/Dashboard/AdminDashboardController.php)
- Legacy/alternate admin dashboard controller: [app/Http/Controllers/Admin/DashboardController.php](app/Http/Controllers/Admin/DashboardController.php)
- User management: [app/Http/Controllers/Admin/UserController.php](app/Http/Controllers/Admin/UserController.php)
- Shared subject controller: [app/Http/Controllers/SubjectController.php](app/Http/Controllers/SubjectController.php)
- Shared question controller: [app/Http/Controllers/QuestionController.php](app/Http/Controllers/QuestionController.php)
- Shared exam controller: [app/Http/Controllers/ExamController.php](app/Http/Controllers/ExamController.php)
- Shared monitoring controller: [app/Http/Controllers/Teacher/LiveMonitoringController.php](app/Http/Controllers/Teacher/LiveMonitoringController.php)
- Shared force-end endpoint: [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php)

## Runtime Sequence

### 1. Admin authenticates and reaches the dashboard

Authentication entry point:

- [app/Http/Controllers/AuthController.php](app/Http/Controllers/AuthController.php)

Relevant detail:

- self-registration only permits `student` and `teacher`
- admins therefore come from seeded data or admin-created accounts, not public registration

Dashboard route:

- `GET /admin/dashboard`

Current routed controller:

- [app/Http/Controllers/Dashboard/AdminDashboardController.php](app/Http/Controllers/Dashboard/AdminDashboardController.php)

This controller aggregates system-wide metrics from:

- [app/Models/User.php](app/Models/User.php)
- [app/Models/Subject.php](app/Models/Subject.php)
- [app/Models/Question.php](app/Models/Question.php)
- [app/Models/Exam.php](app/Models/Exam.php)

It also builds quick-action links to user, subject, question, and exam management.

## Native Admin Flow: User Management

### 2. Admin manages users

Route surface:

- resource routes for `users` in [routes/web.php](routes/web.php)

Controller:

- [app/Http/Controllers/Admin/UserController.php](app/Http/Controllers/Admin/UserController.php)

Access control pattern:

- every public method calls `checkAdmin()`

Supported operations:

1. list users with role and text filters
2. create user with role `student`, `teacher`, or `admin`
3. update user profile and optional password
4. delete user, except the currently logged-in admin cannot delete their own account

Model interaction:

- all operations write to [app/Models/User.php](app/Models/User.php)

This is the only admin flow implemented in a dedicated admin controller namespace.

## Shared Admin Flow: Subjects, Questions, and Exams

### 3. Admin manages subjects via the shared controller

Controller:

- [app/Http/Controllers/SubjectController.php](app/Http/Controllers/SubjectController.php)

How admin differs from teacher:

- the teacher-specific `forTeacher()` filter is skipped for admins
- admins can view and edit any subject

Files involved:

- [app/Models/Subject.php](app/Models/Subject.php)
- [resources/views/subjects/index.blade.php](resources/views/subjects/index.blade.php)
- [resources/views/subjects/create.blade.php](resources/views/subjects/create.blade.php)
- [resources/views/subjects/edit.blade.php](resources/views/subjects/edit.blade.php)
- [resources/views/subjects/show.blade.php](resources/views/subjects/show.blade.php)

### 4. Admin manages questions via the shared controller

Controller:

- [app/Http/Controllers/QuestionController.php](app/Http/Controllers/QuestionController.php)

Admin behavior:

- admin bypasses creator ownership checks
- admin can view, edit, delete, and duplicate any question
- question validation and data shaping are identical to teacher flow

Key downstream consequence:

- admin edits here directly affect future exam assembly and student grading because the shared `Question` storage shape is reused everywhere else

Files involved:

- [app/Models/Question.php](app/Models/Question.php)
- [resources/views/questions/index.blade.php](resources/views/questions/index.blade.php)
- [resources/views/questions/create.blade.php](resources/views/questions/create.blade.php)
- [resources/views/questions/edit.blade.php](resources/views/questions/edit.blade.php)
- [resources/views/questions/show.blade.php](resources/views/questions/show.blade.php)

### 5. Admin manages exams via the shared controller

Controller:

- [app/Http/Controllers/ExamController.php](app/Http/Controllers/ExamController.php)

Admin behavior:

- `authorizeExam()` returns true for admins without teacher ownership checks
- admin can create, edit, publish, archive, delete, and compose any exam
- admin can use the same question-management endpoints used by teachers

This means the admin flow reaches the same pivot and total-mark logic as teacher flow:

- [app/Models/Exam.php](app/Models/Exam.php)
- `exam_questions` pivot through `Exam::questions()`
- [app/Models/Question.php](app/Models/Question.php)

## Shared Admin Flow: Monitoring and Moderation

### 6. Admin can access teacher monitoring routes

The `teacher` middleware in [app/Http/Middleware/TeacherMiddleware.php](app/Http/Middleware/TeacherMiddleware.php) explicitly allows admins.

The monitoring controller in [app/Http/Controllers/Teacher/LiveMonitoringController.php](app/Http/Controllers/Teacher/LiveMonitoringController.php) also permits admins in each access check.

That gives admins these capabilities:

- view monitoring index
- open exam monitoring screens
- fetch live session lists
- start scheduled sessions
- warn students
- allow resume on paused sessions

This is functionally a teacher supervision flow executed under admin authority.

### 7. Admin can force-end a student session

The termination endpoint is not in an admin controller. It lives in:

- [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php)

Authorization logic in `forceEnd()` explicitly allows:

- admins
- the exam’s teacher

When an admin terminates a session, the same event chain is used as in teacher flow:

1. update `ExamSession.status` to `terminated`
2. broadcast `ExamEnded(..., 'terminated_by_teacher')`
3. broadcast `ExamForceEnded`

That event chain reaches:

- teacher/admin monitoring views via `exam.{examId}` and `teacher.{teacherId}`
- the student exam page via `student.{studentId}`

## Event-Centric View of Admin Activity

Admin-specific flows do not introduce an admin-only event channel. Instead, admin-triggered runtime actions reuse the existing teacher/student event topology.

### Event channels currently relevant to admin

- `exam.{examId}`: admin monitoring page receives exam-wide refresh signals
- `teacher.{teacherId}`: admin may indirectly rely on events broadcast to the owning teacher’s channel when viewing monitoring pages
- `student.{studentId}`: admin-triggered moderation actions send commands to the student page

### Events admin can trigger indirectly or directly

| Event | Admin can trigger through | Notes |
|---|---|---|
| `ExamStartAllowed` | [app/Http/Controllers/Teacher/LiveMonitoringController.php](app/Http/Controllers/Teacher/LiveMonitoringController.php) | admin can start scheduled sessions |
| `TeacherWarning` | [app/Http/Controllers/Teacher/LiveMonitoringController.php](app/Http/Controllers/Teacher/LiveMonitoringController.php) | event name remains teacher-oriented even for admin actions |
| `ExamResumed` | [app/Http/Controllers/Teacher/LiveMonitoringController.php](app/Http/Controllers/Teacher/LiveMonitoringController.php) | used for paused sessions |
| `ExamEnded` | [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php) | emitted on forced termination and student submission |
| `ExamForceEnded` | [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php) | direct student-side termination command |

## Files That Interact in This Flow

### Authentication and dashboard

- [app/Http/Controllers/AuthController.php](app/Http/Controllers/AuthController.php)
- [app/Http/Controllers/Dashboard/AdminDashboardController.php](app/Http/Controllers/Dashboard/AdminDashboardController.php)
- [app/Http/Controllers/Admin/DashboardController.php](app/Http/Controllers/Admin/DashboardController.php)
- [resources/views/dashboard/admin/index.blade.php](resources/views/dashboard/admin/index.blade.php)
- [resources/views/admin/dashboard.blade.php](resources/views/admin/dashboard.blade.php)

### Admin-native management

- [app/Http/Controllers/Admin/UserController.php](app/Http/Controllers/Admin/UserController.php)
- [resources/views/users/index.blade.php](resources/views/users/index.blade.php)
- [resources/views/users/create.blade.php](resources/views/users/create.blade.php)
- [resources/views/users/edit.blade.php](resources/views/users/edit.blade.php)

### Shared content management

- [app/Http/Controllers/SubjectController.php](app/Http/Controllers/SubjectController.php)
- [app/Http/Controllers/QuestionController.php](app/Http/Controllers/QuestionController.php)
- [app/Http/Controllers/ExamController.php](app/Http/Controllers/ExamController.php)
- [app/Models/Subject.php](app/Models/Subject.php)
- [app/Models/Question.php](app/Models/Question.php)
- [app/Models/Exam.php](app/Models/Exam.php)

### Shared runtime moderation

- [app/Http/Middleware/TeacherMiddleware.php](app/Http/Middleware/TeacherMiddleware.php)
- [app/Http/Controllers/Teacher/LiveMonitoringController.php](app/Http/Controllers/Teacher/LiveMonitoringController.php)
- [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php)
- [app/Models/ExamSession.php](app/Models/ExamSession.php)
- [app/Models/ViolationLog.php](app/Models/ViolationLog.php)
- [app/Events/ExamStartAllowed.php](app/Events/ExamStartAllowed.php)
- [app/Events/TeacherWarning.php](app/Events/TeacherWarning.php)
- [app/Events/ExamResumed.php](app/Events/ExamResumed.php)
- [app/Events/ExamEnded.php](app/Events/ExamEnded.php)
- [app/Events/ExamForceEnded.php](app/Events/ExamForceEnded.php)

## Refactor Hotspots

- Admin behavior is split across two dashboard controller namespaces: [app/Http/Controllers/Dashboard/AdminDashboardController.php](app/Http/Controllers/Dashboard/AdminDashboardController.php) and [app/Http/Controllers/Admin/DashboardController.php](app/Http/Controllers/Admin/DashboardController.php). Only one is routed today, which suggests a legacy path that should be consolidated.
- Admin uses shared subject/question/exam controllers rather than an explicit admin service boundary. That keeps behavior consistent but makes role-specific refactors harder.
- Admin moderation rides on teacher-named routes, middleware, and event types. The behavior works, but the terminology leaks implementation assumptions into the architecture.
- There is no admin-specific broadcast channel, so admin supervision is not modeled as a first-class runtime role in the event system.
- Authorization is largely manual inside controllers. Policies or form requests would centralize this better.

## Defined But Not Central to Current Admin Runtime

- [app/Events/ExamStarted.php](app/Events/ExamStarted.php): defined but not emitted
- [app/Events/AnswerSaved.php](app/Events/AnswerSaved.php): defined but not used in the active runtime flow
- [app/Events/StudentLeft.php](app/Events/StudentLeft.php): scaffold only
- [app/Events/TeacherForceAction.php](app/Events/TeacherForceAction.php): scaffold only
