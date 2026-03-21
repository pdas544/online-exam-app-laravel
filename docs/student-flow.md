# Student Flow Architecture

This document describes the actual student-side runtime flow in the current codebase, centered on events, controller/model interactions, and the files that participate in the exam lifecycle.

## Scope

- Actor: authenticated user with `role = student`
- Primary journey: open dashboard, click Start Exam, wait for teacher approval, take exam, handle violations, submit exam, return to dashboard
- Main runtime surface: Laravel MVC + Blade + `resources/js/exam-taker.js` + Reverb/Echo channels

## Primary Entry Points

- Student dashboard: [app/Http/Controllers/Dashboard/StudentDashboardController.php](app/Http/Controllers/Dashboard/StudentDashboardController.php)
- Student dashboard view: [resources/views/dashboard/student/index.blade.php](resources/views/dashboard/student/index.blade.php)
- Exam session routes: [routes/web.php](routes/web.php)
- Exam session controller: [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php)
- Exam-taking view: [resources/views/exams/take.blade.php](resources/views/exams/take.blade.php)
- Exam-taking client: [resources/js/exam-taker.js](resources/js/exam-taker.js)
- Echo/Reverb bootstrap: [resources/js/bootstrap.js](resources/js/bootstrap.js)

## Runtime Sequence

### 1. Student lands on the dashboard

The dashboard is assembled in [app/Http/Controllers/Dashboard/StudentDashboardController.php](app/Http/Controllers/Dashboard/StudentDashboardController.php). That controller computes four key collections:

- `resumeExams`: sessions already in `in_progress`
- `availableExams`: published exams inside availability windows, excluding in-progress sessions and exams where max attempts were reached
- `upcomingExams`
- `pastResults`

The Start Exam CTA is rendered in [resources/views/dashboard/student/index.blade.php](resources/views/dashboard/student/index.blade.php) as a link to `route('exam.start', $exam['id'])`.

### 2. Student clicks Start Exam

Route:

- `GET /exam/{exam}/start` in [routes/web.php](routes/web.php)

Controller method:

- `ExamSessionController::start()` in [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php)

This method performs the following checks and writes:

1. Calls `Exam::isAvailable()` from [app/Models/Exam.php](app/Models/Exam.php) to ensure the exam is published and within the time window.
2. Looks for an existing `ExamSession` for the same student and exam in statuses `scheduled`, `in_progress`, `paused`, or `completed`.
3. Redirects to resume if an unfinished session exists.
4. Rejects if a completed session already exists.
5. Loads the exam questions ordered by `exam_questions.order_index` through the `Exam::questions()` relationship.
6. Creates one `ExamSession` row in [app/Models/ExamSession.php](app/Models/ExamSession.php) with status `scheduled`.
7. Creates one `StudentAnswer` row per question in [app/Models/StudentAnswer.php](app/Models/StudentAnswer.php), using pivot `points_override` when present.

This is the first durable handoff between exam configuration and student runtime state.

### 3. Session creation emits the first event

After the transaction commits, the controller broadcasts:

- `StudentJoined` from [app/Events/StudentJoined.php](app/Events/StudentJoined.php)

Event payload includes:

- `examId`
- `sessionId`
- `teacher_id`
- `student_id`
- `student_name`
- `status = scheduled`

Broadcast channels come from [app/Events/BaseExamEvent.php](app/Events/BaseExamEvent.php):

- `exam.{examId}`
- `teacher.{teacherId}`

Current consumers:

- Inline monitoring JS in [resources/views/dashboard/teacher/monitoring/exam.blade.php](resources/views/dashboard/teacher/monitoring/exam.blade.php) refreshes the live session table on `.student.joined`.

### 4. Student is redirected to the exam-taking page

Route:

- `GET /exam/session/{session}/take`

Controller method:

- `ExamSessionController::take()` in [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php)

The controller authorizes session ownership and eager-loads:

- `session.exam`
- `session.exam.questions`
- `session.answers.question`

The Blade view [resources/views/exams/take.blade.php](resources/views/exams/take.blade.php) serializes runtime config into `#exam-container`, including:

- `studentId`
- CSRF token
- auto-save interval
- `startLocked = ($session->status === 'scheduled')`

This means a newly created session always opens in a waiting-room state until teacher approval changes the session status.

### 5. Exam-taking frontend initializes

Client bootstrap:

- [resources/js/bootstrap.js](resources/js/bootstrap.js) initializes Echo/Reverb.
- [resources/js/exam-taker.js](resources/js/exam-taker.js) instantiates `ExamTaker` on `DOMContentLoaded`.

`ExamTaker` sets up:

- question navigation
- answer save listeners
- mark-for-review state
- auto-save timer
- countdown timer
- violation detection
- WebSocket listeners
- unload handling

If `startLocked` is true, the lobby modal remains open and the actual exam timer does not start.

### 6. Teacher approval unlocks the session

Teacher action route:

- `POST /teacher/monitor/{exam}/start`

Controller:

- `LiveMonitoringController::startExam()` in [app/Http/Controllers/Teacher/LiveMonitoringController.php](app/Http/Controllers/Teacher/LiveMonitoringController.php)

For every scheduled session, the controller updates:

- `status = in_progress`
- `started_at = now()`
- `last_activity_at = now()`

It then broadcasts:

- `ExamStartAllowed` from [app/Events/ExamStartAllowed.php](app/Events/ExamStartAllowed.php)

Channels:

- `exam.{examId}`
- `teacher.{teacherId}`

Student-side consumer:

- [resources/js/exam-taker.js](resources/js/exam-taker.js) listens on `exam.{examId}` for `.exam.start.allowed`
- it filters by `sessionId`
- it enables the Proceed button in the lobby modal
- when the student clicks Proceed, `startExamFlow()` starts auto-save, violations, and countdown

## Answer-Saving Flow

### 7. Student answers a question

Client action:

- radio change
- checkbox change
- text input debounce
- manual Save button
- mark-for-review button

All of these converge into `ExamTaker.saveAnswer()` in [resources/js/exam-taker.js](resources/js/exam-taker.js), which posts to:

- `POST /exam/session/{session}/answer`

Controller method:

- `ExamSessionController::saveAnswer()` in [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php)

Server-side steps:

1. Authorize session ownership.
2. Validate `question_id`, `answer`, and `is_marked_for_review`.
3. Load the `StudentAnswer` row for the session/question pair.
4. Update `answer`, `is_answered`, `is_marked_for_review`, and `answered_at`.
5. Call `ExamSession::updateProgress()` in [app/Models/ExamSession.php](app/Models/ExamSession.php).
6. Return JSON progress counts.

Model interactions:

- [app/Models/StudentAnswer.php](app/Models/StudentAnswer.php)
- [app/Models/ExamSession.php](app/Models/ExamSession.php)

Frontend reactions:

- update answered count
- update progress bar
- update palette icon/status for the question

## Important Current-State Note

`ExamSessionController` imports `AnswerSaved`, and [app/Events/AnswerSaved.php](app/Events/AnswerSaved.php) exists, but the controller does not currently broadcast it. The event also depends on `student_id` in its payload, but that field is never populated in the current constructor. Treat this event as defined but not part of the active runtime path.

## Violation and Pause Flow

### 8. The browser detects suspicious behavior

Violation detection is implemented entirely in [resources/js/exam-taker.js](resources/js/exam-taker.js). Current triggers include:

- tab switch
- window blur
- fullscreen exit
- Tab key press
- resize
- page navigation attempt
- new tab/window shortcuts
- minimize heuristic
- copy
- paste
- context menu prevention

Focus-loss violations call `handleFocusLoss()`, which:

1. sets `paused = true`
2. stops timers
3. opens the pause modal
4. sends the violation to the server

Route:

- `POST /exam/session/{session}/violation`

Controller method:

- `ExamSessionController::logViolation()` in [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php)

Server-side steps:

1. Validate `type`, `description`, and optional metadata.
2. Call `ExamSession::logViolation()`.
3. Persist a `ViolationLog` row in [app/Models/ViolationLog.php](app/Models/ViolationLog.php).
4. Increment `ExamSession.violation_count`.
5. Auto-terminate the session at 5+ violations inside the model.
6. For focus-loss types, change session status to `paused` if it was `in_progress`.
7. Broadcast `ViolationDetected` from [app/Events/ViolationDetected.php](app/Events/ViolationDetected.php).

Channels:

- `exam.{examId}`
- `teacher.{teacherId}`

Teacher-side consumers:

- inline JS in [resources/views/dashboard/teacher/monitoring/exam.blade.php](resources/views/dashboard/teacher/monitoring/exam.blade.php) shows an alert and refreshes the session table

Student-side result:

- if server reports `terminated = true`, the client redirects to the dashboard
- otherwise the student stays paused and waits for teacher approval

## Resume, Warning, and Force-End Flow

### 9. Teacher sends a warning

Route:

- `POST /teacher/monitor/session/{session}/warn`

Controller:

- `LiveMonitoringController::sendWarning()` in [app/Http/Controllers/Teacher/LiveMonitoringController.php](app/Http/Controllers/Teacher/LiveMonitoringController.php)

Event:

- `TeacherWarning` from [app/Events/TeacherWarning.php](app/Events/TeacherWarning.php)

Channel:

- `student.{studentId}`

Student consumer:

- [resources/js/exam-taker.js](resources/js/exam-taker.js) listens for `.teacher.warning` and renders a toast-style warning banner

### 10. Teacher allows resume

Route:

- `POST /teacher/monitor/session/{session}/resume`

Controller:

- `LiveMonitoringController::resumeSession()` in [app/Http/Controllers/Teacher/LiveMonitoringController.php](app/Http/Controllers/Teacher/LiveMonitoringController.php)

Server-side steps:

1. Verify teacher/admin ownership.
2. If session status is `paused`, change it back to `in_progress`.
3. Broadcast `ExamResumed` from [app/Events/ExamResumed.php](app/Events/ExamResumed.php).

Channel:

- `student.{studentId}`

Student consumer:

- [resources/js/exam-taker.js](resources/js/exam-taker.js) listens for `.exam.resume`
- it enables the Resume button
- once clicked, the client restarts auto-save and the timer

### 11. Teacher or admin force-ends the exam

Route:

- `POST /teacher/monitor/session/{session}/end`

Controller:

- `ExamSessionController::forceEnd()` in [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php)

Server-side steps:

1. Authorize admin or owning teacher.
2. Set `status = terminated` and `submitted_at = now()`.
3. Broadcast `ExamEnded($session, 'terminated_by_teacher')`.
4. Broadcast `ExamForceEnded($session)`.

Channels:

- `ExamEnded`: `exam.{examId}`, `teacher.{teacherId}`
- `ExamForceEnded`: `student.{studentId}`

Student consumer:

- [resources/js/exam-taker.js](resources/js/exam-taker.js) listens for `.exam.forceEnd`
- it stops timers, shows an alert, and redirects to `/student/dashboard?ended=1`

## Submission and Grading Flow

### 12. Student submits the exam

Route:

- `POST /exam/session/{session}/submit`

Controller:

- `ExamSessionController::submit()` in [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php)

Server-side steps:

1. Authorize session ownership.
2. Require current status `in_progress`.
3. Compute time spent from `started_at`.
4. Update session to `completed` with `submitted_at` and `time_spent`.
5. Load answers and related questions.
6. Auto-grade each answer through `StudentAnswer::autoGrade()` in [app/Models/StudentAnswer.php](app/Models/StudentAnswer.php).
7. Aggregate `points_earned` and `max_points`.
8. Calculate percentage score.
9. Set `score` and `passed` using the exam’s `passing_marks`.
10. Broadcast `ExamEnded($session, 'completed')`.
11. Return JSON redirect to the student dashboard.

Question-type grading rules come from [app/Models/StudentAnswer.php](app/Models/StudentAnswer.php) and depend on data shapes defined in [app/Models/Question.php](app/Models/Question.php).

### 13. Student returns to dashboard

The dashboard recomputes:

- completed count
- in-progress count
- average score
- recent results

This closes the loop back to [app/Http/Controllers/Dashboard/StudentDashboardController.php](app/Http/Controllers/Dashboard/StudentDashboardController.php) and [resources/views/dashboard/student/index.blade.php](resources/views/dashboard/student/index.blade.php).

## Event Matrix

| Event | Emitted from | Trigger | Channels | Current consumer |
|---|---|---|---|---|
| `StudentJoined` | [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php) | Session created | `exam.{examId}`, `teacher.{teacherId}` | [resources/views/dashboard/teacher/monitoring/exam.blade.php](resources/views/dashboard/teacher/monitoring/exam.blade.php) |
| `ExamStartAllowed` | [app/Http/Controllers/Teacher/LiveMonitoringController.php](app/Http/Controllers/Teacher/LiveMonitoringController.php) | Teacher starts scheduled sessions | `exam.{examId}`, `teacher.{teacherId}` | [resources/js/exam-taker.js](resources/js/exam-taker.js), [resources/views/dashboard/teacher/monitoring/exam.blade.php](resources/views/dashboard/teacher/monitoring/exam.blade.php) |
| `ViolationDetected` | [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php) | Violation logged | `exam.{examId}`, `teacher.{teacherId}` | [resources/views/dashboard/teacher/monitoring/exam.blade.php](resources/views/dashboard/teacher/monitoring/exam.blade.php) |
| `TeacherWarning` | [app/Http/Controllers/Teacher/LiveMonitoringController.php](app/Http/Controllers/Teacher/LiveMonitoringController.php) | Teacher warning | `student.{studentId}` | [resources/js/exam-taker.js](resources/js/exam-taker.js) |
| `ExamResumed` | [app/Http/Controllers/Teacher/LiveMonitoringController.php](app/Http/Controllers/Teacher/LiveMonitoringController.php) | Teacher resumes paused session | `student.{studentId}` | [resources/js/exam-taker.js](resources/js/exam-taker.js) |
| `ExamEnded` | [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php) | Student submit or force end | `exam.{examId}`, `teacher.{teacherId}` | [resources/views/dashboard/teacher/monitoring/exam.blade.php](resources/views/dashboard/teacher/monitoring/exam.blade.php) |
| `ExamForceEnded` | [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php) | Teacher/admin termination | `student.{studentId}` | [resources/js/exam-taker.js](resources/js/exam-taker.js) |

## Files That Interact in This Flow

### Routes and access control

- [routes/web.php](routes/web.php)
- [app/Http/Middleware/TeacherMiddleware.php](app/Http/Middleware/TeacherMiddleware.php)

### Controllers

- [app/Http/Controllers/Dashboard/StudentDashboardController.php](app/Http/Controllers/Dashboard/StudentDashboardController.php)
- [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php)
- [app/Http/Controllers/Teacher/LiveMonitoringController.php](app/Http/Controllers/Teacher/LiveMonitoringController.php)

### Models

- [app/Models/Exam.php](app/Models/Exam.php)
- [app/Models/ExamSession.php](app/Models/ExamSession.php)
- [app/Models/StudentAnswer.php](app/Models/StudentAnswer.php)
- [app/Models/Question.php](app/Models/Question.php)
- [app/Models/ViolationLog.php](app/Models/ViolationLog.php)
- [app/Models/User.php](app/Models/User.php)

### Events

- [app/Events/BaseExamEvent.php](app/Events/BaseExamEvent.php)
- [app/Events/StudentJoined.php](app/Events/StudentJoined.php)
- [app/Events/ExamStartAllowed.php](app/Events/ExamStartAllowed.php)
- [app/Events/ViolationDetected.php](app/Events/ViolationDetected.php)
- [app/Events/TeacherWarning.php](app/Events/TeacherWarning.php)
- [app/Events/ExamResumed.php](app/Events/ExamResumed.php)
- [app/Events/ExamEnded.php](app/Events/ExamEnded.php)
- [app/Events/ExamForceEnded.php](app/Events/ExamForceEnded.php)

### Views and client code

- [resources/views/dashboard/student/index.blade.php](resources/views/dashboard/student/index.blade.php)
- [resources/views/exams/take.blade.php](resources/views/exams/take.blade.php)
- [resources/js/bootstrap.js](resources/js/bootstrap.js)
- [resources/js/exam-taker.js](resources/js/exam-taker.js)

## Refactor Hotspots

- Session state transitions are split between server and browser. The server owns persistence, but the browser decides when to start timers and pause UI, which makes reasoning about source-of-truth harder.
- Violation handling mixes detection, UI pausing, persistence, and moderation signaling in one client class and one controller.
- `saveAnswer()` updates progress but does not emit the existing `AnswerSaved` event, so the event catalog and the actual flow are inconsistent.
- Several route/controller responses redirect to `dashboard`, `student.dashboard`, or raw strings, which makes the return path inconsistent.
- `ExamSessionController::result()` references `view('exam.result')`; verify whether the view exists before relying on that path in future refactors.
- The session starts as `scheduled`, but the exam page is already accessible before teacher approval. That is intentional in the current flow, but it couples lobby behavior tightly to the frontend.

## Defined But Not Active in Current Student Runtime

- [app/Events/AnswerSaved.php](app/Events/AnswerSaved.php): defined and imported, not broadcast today
- [app/Events/ExamStarted.php](app/Events/ExamStarted.php): defined, not emitted today
- [app/Events/StudentLeft.php](app/Events/StudentLeft.php): scaffold only
- [app/Events/TeacherForceAction.php](app/Events/TeacherForceAction.php): scaffold only
