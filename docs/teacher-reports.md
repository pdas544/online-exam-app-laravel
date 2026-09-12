# Teacher Reports Implementation Plan

> Scope: new `teacher/reports` section with offline-exam-equivalent reporting
> (result register, summary, question analysis, student card), on-screen plus
> CSV and PDF export, with truthful "aborted" classification.

## 1. What exists today vs what is missing

- Teacher has live monitoring but **no reports surface**: zero `report`
  routes, and `dashboard/admin/reports.blade.php` is an empty placeholder.
  Scores/answers/violations/grading state are all stored and sufficient —
  no grading or schema changes needed beyond end-reason columns.
- There is **no reason column** anywhere: teacher force-end, violation
  auto-terminate, and scheduler expiry all write bare statuses, and crashes
  write nothing at all.

## 2. "Aborted" definition (agreed)

- Teacher aborts / force-ends an exam before completion, OR the exam ended
  automatically due to unknown errors.
- Honest accounting: crashes leave no trace by definition, so retroactive
  crash detection is excluded. Every KNOWN end path records a reason;
  anything else reports under an explicit "unrecorded" bucket.

## 3. Architecture and files

- **Migration**: add nullable `end_reason` + `ended_by` to `exam_sessions`;
  best-effort backfill (terminated + `auto_terminated` log → `auto_terminated`,
  other terminated → `force_ended`, flagged as approximate in reports).
- **Writes at all 3 end paths**: `ExamSessionService::forceEnd` (reason +
  teacher id), `ExamSession::logViolation` auto-terminate branch (reason),
  `expireOverdue` (reason).
- **New `ReportService`** (aggregates, unit-tested) + new
  `Teacher\ReportController` under the existing `teacher` middleware group
  (`teacher.reports.*` routes), following the `LiveMonitoringController`
  auth pattern (own exams only, admin sees all — mirror `paginateFor`).
- **4 reports, blades with shared table partials**:
  - R1 result register — per student/session: attempts, status + end reason,
    earned/total, %, pass/fail, violations, time.
  - R2 summary — appeared/passed/failed, mean/median/min/max, pass %,
    ungraded-pending count.
  - R3 question analysis — per question: attempt %, correct % (difficulty),
    avg time, MCQ distractor split.
  - R4 student card — per-attempt detail extending the existing
    `studentResultDetail` shape, with Not-attempted kept distinct from Wrong
    via `is_answered` (the student view currently collapses them).
- **CSV**: zero-dependency streamed `fputcsv` responses.
- **PDF**: new `barryvdh/laravel-dompdf` dependency (pure PHP — chosen over
  Snappy because Snappy needs a system `wkhtmltopdf` binary this
  self-hosted box doesn't have), rendering the same Blade tables
  print-styled.

## 4. Deliberate exclusions

- **Absentee roster**: students aren't enrolled per exam (anyone can start),
  so "absent" is undefinable without a roster feature.
- **Retroactive crash detection**: impossible by definition; the
  "unrecorded" bucket is the substitute.

## 5. Data gotchas the implementer must respect

- `exam_sessions.score` stores a **percentage (0–100), not raw marks**.
- Post-grade, `is_correct`/`points_earned` alone cannot separate unanswered
  from wrong — must predicate on `is_answered` (or empty `answer`).
- Terminated/expired sessions are never graded (`GradeExamSession` no-ops
  unless `status == completed`).

## 6. Task sequence (TDD throughout)

1. Migration + backfill + model fillable/casts (verify on both drivers).
2. End-reason writes at the 3 paths + unit tests (force-end records teacher
   id; 5th violation records auto-terminate; expiry records expired).
3. `ReportService` R1–R4 queries + unit tests (incl. the score-is-percentage
   and unanswered-vs-wrong rules).
4. Controller + routes + blades (teacher scope as above).
5. CSV endpoints + tests (content, filename, row parity with screen).
6. dompdf dep + PDF endpoints + tests (200, `%PDF` header, row parity).
7. Full suite + pint + manual 2-role check (teacher sees own,
   stranger-teacher 403).

## 7. Open decisions (defaults if unanswered)

- R4 student card ships in v1 (cheap — reuses existing detail logic).
- PDF covers all 4 reports (register-only would cover printouts for ~30%
  less template work; confirm if scope must shrink).
- Backfill approximation accepted as flagged above.
