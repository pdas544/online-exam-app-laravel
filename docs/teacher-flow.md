# Teacher Flow Architecture

This document describes the teacher-side architecture for exam authoring, question assembly, live monitoring, and moderation. It focuses on which files collaborate and where event-driven behavior is currently implemented.

## Scope

- Actor: authenticated user with `role = teacher`
- Secondary actor: admin, because many teacher monitoring actions explicitly allow admin access too
- Main runtime areas: dashboard, subject/question/exam CRUD, exam question composition, live monitoring, moderation events

## Primary Entry Points

- Teacher dashboard: [app/Http/Controllers/Dashboard/TeacherDashboardController.php](app/Http/Controllers/Dashboard/TeacherDashboardController.php)
- Exam CRUD: [app/Http/Controllers/ExamController.php](app/Http/Controllers/ExamController.php)
- Subject CRUD: [app/Http/Controllers/SubjectController.php](app/Http/Controllers/SubjectController.php)
- Question CRUD: [app/Http/Controllers/QuestionController.php](app/Http/Controllers/QuestionController.php)
- Live monitoring controller: [app/Http/Controllers/Teacher/LiveMonitoringController.php](app/Http/Controllers/Teacher/LiveMonitoringController.php)
- Forced termination endpoint: [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php)
- Live monitoring UI: [resources/views/dashboard/teacher/monitoring/exam.blade.php](resources/views/dashboard/teacher/monitoring/exam.blade.php)

## Runtime Sequence

### 1. Teacher lands on the dashboard

Route:

- `GET /teacher/dashboard` in [routes/web.php](routes/web.php)

Controller:

- [app/Http/Controllers/Dashboard/TeacherDashboardController.php](app/Http/Controllers/Dashboard/TeacherDashboardController.php)

The dashboard builds teacher-facing stats and navigation from these models:

- [app/Models/Subject.php](app/Models/Subject.php)
- [app/Models/Question.php](app/Models/Question.php)
- [app/Models/Exam.php](app/Models/Exam.php)
- [app/Models/ExamSession.php](app/Models/ExamSession.php)

Key outputs:

- subject count owned by the teacher
- question count owned by the teacher
- total and published exam counts
- recent exam/question activity
- upcoming exams

## Content Authoring Flow

### 2. Teacher creates and manages subjects

Route surface:

- resource routes for `subjects` in [routes/web.php](routes/web.php)

Controller:

- [app/Http/Controllers/SubjectController.php](app/Http/Controllers/SubjectController.php)

Behavior:

- teachers see only subjects where `created_by = Auth::id()`
- admins bypass that filter
- create/update/delete flows write to [app/Models/Subject.php](app/Models/Subject.php)

This is the root content container for both questions and exams.

### 3. Teacher creates and manages questions

Route surface:

- resource routes for `questions`
- duplicate endpoint `/questions/{question}/duplicate`

Controller:

- [app/Http/Controllers/QuestionController.php](app/Http/Controllers/QuestionController.php)

Models involved:

- [app/Models/Question.php](app/Models/Question.php)
- [app/Models/Subject.php](app/Models/Subject.php)
- [app/Models/User.php](app/Models/User.php)

Important logic:

1. non-admin teachers are scoped to their own questions
2. validation differs by `question_type`
3. `processQuestionData()` normalizes the storage shape used later by auto-grading
4. MCQ options become a JSON map like `{"A":"...","B":"..."}`
5. `correct_answers` is stored as JSON arrays for all types

This controller is upstream of every student grading path because `StudentAnswer::autoGrade()` depends on these shapes.

### 4. Teacher creates and manages exams

Route surface:

- resource routes for `exams`

Controller:

- [app/Http/Controllers/ExamController.php](app/Http/Controllers/ExamController.php)

Models involved:

- [app/Models/Exam.php](app/Models/Exam.php)
- [app/Models/Subject.php](app/Models/Subject.php)

Important responsibilities:

- create/update exam metadata
- upload optional instruction file
- authorize by `teacher_id`, unless admin
- block deletion once an exam has started or completed sessions

### 5. Teacher assembles the exam question set

Routes:

- `GET /exams/{exam}/questions`
- `POST /exams/{exam}/questions`
- `POST /exams/{exam}/questions/bulk`
- `DELETE /exams/{exam}/questions/{question}`
- `POST /exams/{exam}/questions/reorder`
- `PUT /exams/{exam}/questions/{question}/points`

Controller methods:

- `manageQuestions()`
- `addQuestion()`
- `bulkAddQuestions()`
- `removeQuestion()`
- `reorderQuestions()`
- `updatePoints()`

These methods manipulate the `exam_questions` pivot through `Exam::questions()` in [app/Models/Exam.php](app/Models/Exam.php) and then recalculate totals with `Exam::updateTotalMarks()`.

This is the structural layer that determines:

- question order
- question-specific points override
- exam total marks

Any refactor of student scoring or exam sequencing will touch this layer first.

## Monitoring and Moderation Flow

### 6. Teacher opens live monitoring

Routes:

- `GET /teacher/monitor`
- `GET /teacher/monitor/{exam}`
- `GET /teacher/monitor/{exam}/sessions`

Controller:

- [app/Http/Controllers/Teacher/LiveMonitoringController.php](app/Http/Controllers/Teacher/LiveMonitoringController.php)

Access control:

- [app/Http/Middleware/TeacherMiddleware.php](app/Http/Middleware/TeacherMiddleware.php) permits both teacher and admin
- controller methods also manually check `exam->teacher_id === Auth::id()` unless the user is admin

Data shape for the monitoring screen is built from:

- [app/Models/Exam.php](app/Models/Exam.php)
- [app/Models/ExamSession.php](app/Models/ExamSession.php)
- `ExamSession->student`
- `ExamSession->answers`

The monitoring view [resources/views/dashboard/teacher/monitoring/exam.blade.php](resources/views/dashboard/teacher/monitoring/exam.blade.php) contains its own inline JS for polling and Echo listeners.

### 7. Teacher sees student session creation in real time

When a student starts an exam, [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php) broadcasts `StudentJoined`.

The monitoring page listens on:

- `exam.{examId}` for `.student.joined`
- `teacher.{teacherId}` for `.student.joined`

The current implementation responds by calling `refreshSessions()` and re-fetching session data from:

- `LiveMonitoringController::getSessions()` in [app/Http/Controllers/Teacher/LiveMonitoringController.php](app/Http/Controllers/Teacher/LiveMonitoringController.php)

This means the real-time layer is used as an invalidation signal, not as the source of truth for row rendering.

### 8. Teacher starts the exam for waiting students

Route:

- `POST /teacher/monitor/{exam}/start`

Controller method:

- `LiveMonitoringController::startExam()` in [app/Http/Controllers/Teacher/LiveMonitoringController.php](app/Http/Controllers/Teacher/LiveMonitoringController.php)

Server-side steps:

1. load all `scheduled` sessions for the exam
2. update each session to `in_progress`
3. set `started_at` and `last_activity_at`
4. broadcast `ExamStartAllowed`

Event file:

- [app/Events/ExamStartAllowed.php](app/Events/ExamStartAllowed.php)

Channels:

- `exam.{examId}`
- `teacher.{teacherId}`

Consumers:

- student page in [resources/js/exam-taker.js](resources/js/exam-taker.js)
- monitoring page in [resources/views/dashboard/teacher/monitoring/exam.blade.php](resources/views/dashboard/teacher/monitoring/exam.blade.php) refreshes its table on the event

### 9. Teacher watches progress and violations

The monitoring page has two data inputs:

- polling every few seconds against `/teacher/monitor/{exam}/sessions`
- Echo listeners for refresh-worthy events

`getSessions()` returns flattened runtime data per session:

- student name/email
- status
- answered count
- total questions
- live time spent
- violation count
- last activity

Violation events are emitted by `ExamSessionController::logViolation()` and materialized as `ViolationDetected` from [app/Events/ViolationDetected.php](app/Events/ViolationDetected.php).

Channels:

- `exam.{examId}`
- `teacher.{teacherId}`

Teacher-side effect:

- show alert banner
- refresh table

### 10. Teacher sends a warning

Route:

- `POST /teacher/monitor/session/{session}/warn`

Controller method:

- `LiveMonitoringController::sendWarning()`

Event:

- `TeacherWarning` from [app/Events/TeacherWarning.php](app/Events/TeacherWarning.php)

Channel:

- `student.{studentId}`

Teacher monitoring UI uses the server response only as completion confirmation; the student page consumes the event and displays the warning.

### 11. Teacher allows a paused student to resume

Route:

- `POST /teacher/monitor/session/{session}/resume`

Controller method:

- `LiveMonitoringController::resumeSession()`

Server-side behavior:

1. if session status is `paused`, change it to `in_progress`
2. broadcast `ExamResumed`

Event file:

- [app/Events/ExamResumed.php](app/Events/ExamResumed.php)

Channel:

- `student.{studentId}`

This is the moderation counterpart to the student-side pause modal.

### 12. Teacher force-ends a student session

Route:

- `POST /teacher/monitor/session/{session}/end`

Controller method:

- `ExamSessionController::forceEnd()` in [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php)

Server-side behavior:

1. authorize teacher ownership or admin override
2. set status to `terminated`
3. set `submitted_at`
4. broadcast `ExamEnded(..., 'terminated_by_teacher')`
5. broadcast `ExamForceEnded`

Teacher-facing result:

- monitoring table refreshes through `.exam.ended`

Student-facing result:

- student page receives `.exam.forceEnd` and redirects away

### 13. Teacher sees exam submission completion

When a student submits successfully, `ExamSessionController::submit()` broadcasts `ExamEnded(..., 'completed')`.

Teacher monitoring consumes `.exam.ended` and refreshes the session list.

This is how completion reaches the monitoring view today. The UI still relies on a refetch instead of rendering directly from the event payload.

## Event Matrix

| Event | Emitted by | Why it exists in teacher flow | Channels | Teacher-side consumer |
|---|---|---|---|---|
| `StudentJoined` | [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php) | new scheduled session exists | `exam.{examId}`, `teacher.{teacherId}` | [resources/views/dashboard/teacher/monitoring/exam.blade.php](resources/views/dashboard/teacher/monitoring/exam.blade.php) |
| `ExamStartAllowed` | [app/Http/Controllers/Teacher/LiveMonitoringController.php](app/Http/Controllers/Teacher/LiveMonitoringController.php) | teacher opens the exam for scheduled sessions | `exam.{examId}`, `teacher.{teacherId}` | [resources/views/dashboard/teacher/monitoring/exam.blade.php](resources/views/dashboard/teacher/monitoring/exam.blade.php) |
| `ViolationDetected` | [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php) | moderation signal for suspicious behavior | `exam.{examId}`, `teacher.{teacherId}` | [resources/views/dashboard/teacher/monitoring/exam.blade.php](resources/views/dashboard/teacher/monitoring/exam.blade.php) |
| `ExamEnded` | [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php) | completion or termination | `exam.{examId}`, `teacher.{teacherId}` | [resources/views/dashboard/teacher/monitoring/exam.blade.php](resources/views/dashboard/teacher/monitoring/exam.blade.php) |
| `TeacherWarning` | [app/Http/Controllers/Teacher/LiveMonitoringController.php](app/Http/Controllers/Teacher/LiveMonitoringController.php) | direct student moderation | `student.{studentId}` | no teacher-side listener required |
| `ExamResumed` | [app/Http/Controllers/Teacher/LiveMonitoringController.php](app/Http/Controllers/Teacher/LiveMonitoringController.php) | direct student resume approval | `student.{studentId}` | no teacher-side listener required |
| `ExamForceEnded` | [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php) | direct student termination command | `student.{studentId}` | no teacher-side listener required |

## Files That Interact in This Flow

### Routes and middleware

- [routes/web.php](routes/web.php)
- [app/Http/Middleware/TeacherMiddleware.php](app/Http/Middleware/TeacherMiddleware.php)

### Dashboard and CRUD controllers

- [app/Http/Controllers/Dashboard/TeacherDashboardController.php](app/Http/Controllers/Dashboard/TeacherDashboardController.php)
- [app/Http/Controllers/SubjectController.php](app/Http/Controllers/SubjectController.php)
- [app/Http/Controllers/QuestionController.php](app/Http/Controllers/QuestionController.php)
- [app/Http/Controllers/ExamController.php](app/Http/Controllers/ExamController.php)

### Live runtime controllers

- [app/Http/Controllers/Teacher/LiveMonitoringController.php](app/Http/Controllers/Teacher/LiveMonitoringController.php)
- [app/Http/Controllers/ExamSessionController.php](app/Http/Controllers/ExamSessionController.php)

### Models

- [app/Models/Subject.php](app/Models/Subject.php)
- [app/Models/Question.php](app/Models/Question.php)
- [app/Models/Exam.php](app/Models/Exam.php)
- [app/Models/ExamSession.php](app/Models/ExamSession.php)
- [app/Models/StudentAnswer.php](app/Models/StudentAnswer.php)
- [app/Models/ViolationLog.php](app/Models/ViolationLog.php)

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

- [resources/views/dashboard/teacher/index.blade.php](resources/views/dashboard/teacher/index.blade.php)
- [resources/views/dashboard/teacher/monitoring/index.blade.php](resources/views/dashboard/teacher/monitoring/index.blade.php)
- [resources/views/dashboard/teacher/monitoring/exam.blade.php](resources/views/dashboard/teacher/monitoring/exam.blade.php)
- [resources/views/exams/questions.blade.php](resources/views/exams/questions.blade.php)
- [resources/js/exams/exam-questions.js](resources/js/exams/exam-questions.js)
- [resources/js/bootstrap.js](resources/js/bootstrap.js)

## Refactor Hotspots

- Monitoring logic is split between server-generated Blade inline scripts and a separate file [resources/js/teacher-monitor.js](resources/js/teacher-monitor.js). The blade template appears to be the active implementation; the standalone JS listens for `.exam.started`, which is not currently emitted.
- Access control is duplicated between middleware and controller methods. A policy or gate layer would reduce role-check drift.
- `ExamController` mixes CRUD responsibilities with exam-question orchestration. Splitting exam metadata management from exam composition would make the lifecycle easier to maintain.
- The teacher dashboard computes an `activeSessions` query and then overwrites it with `null`, which suggests unfinished or stale logic.
- Real-time events are mostly used as a refresh trigger instead of updating the UI directly from payloads. That keeps the UI correct but adds repeated polling/refetch coupling.

## Defined But Not Active in Current Teacher Runtime

- [app/Events/ExamStarted.php](app/Events/ExamStarted.php): defined but never broadcast
- [app/Events/AnswerSaved.php](app/Events/AnswerSaved.php): defined but not emitted from answer-saving flow
- [resources/js/teacher-monitor.js](resources/js/teacher-monitor.js): appears stale relative to the current Blade monitoring page
- [app/Events/StudentLeft.php](app/Events/StudentLeft.php): scaffold only
- [app/Events/TeacherForceAction.php](app/Events/TeacherForceAction.php): scaffold only
