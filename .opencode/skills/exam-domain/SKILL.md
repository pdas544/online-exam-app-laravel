---
name: exam-domain
description: Use when working on exam, question, session, answer, grading, or violation logic. Encodes the domain shapes and rules the whole app hangs on. Triggers on keywords: question_type, correct_answers, options, auto-grade, negative marking, max_attempts, violation, terminate, passing_marks, total_marks.
---

# Exam Domain Rules

## Canonical shapes (keep exact)

- `question_type ∈ {mcq_single, mcq_multiple, true_false, fill_blank}`.
- MCQ `options` = JSON map `{"A": "...", "B": "..."}`.
- `correct_answers` = JSON array (single-element for `mcq_single`).
- Pivot `exam_question`: `order_index`, `points_override`. After any pivot
  order/points change, call `Exam::updateTotalMarks()`.

## Grading (`GradingService`)

- `mcq_single`: full points iff selected == the single correct answer.
- `mcq_multiple`: exact set match → full points; anything else → 0
  (no partial credit — deliberate, changing it alters published scores).
- `true_false`: case-insensitive string compare after trim.
- `fill_blank`: trim + case-insensitive; multiple accepted answers supported.
- Optional negative marking: `negative_marks` fraction of question points,
  only when enabled **and** the answer is wrong (never on blank/unanswered).
- Unanswered → `is_correct=false`, `points_earned=0`.

## Sessions (`ExamSessionService`)

- Availability gate: `is_published && now ∈ [available_from, available_until]`.
- Attempt counting: `max_attempts` enforced against **completed** sessions only.
- Start resumes an existing active (`scheduled/in_progress/paused`) session —
  never create a duplicate.
- Submit is idempotent: re-submitting `completed` returns the session.
- `passed = score >= exam.passing_marks` (default 40); score is % of earned /
  possible, rounded to 2dp.
- Timer: `timeRemaining()` with absolute diff (see `laravel-testing` Carbon-3
  note). Expired active sessions → `expired` via `expireOverdue()` (scheduler).

## Violations (`ViolationService`)

- Severities: `low` (1pt: tab_switch, window_blur, copy_paste, right_click),
  `medium` (2pt: fullscreen_exit, multiple_faces, no_face, tab_key),
  `high` (3pt: phone_detected, another_person, forbidden_app).
  Unknown types default to `low`.
- Auto-terminate at **≥ 5 points** (not count). Check status after recording.
- Focus-loss types (`tab_switch`, `window_blur`, `fullscreen_exit`, `tab_key`)
  pause an `in_progress` session; never pause a terminated/expired one.

## Frontend contract

`exam-taker.js` needs `.question-card[data-question-id]` and the session AJAX
routes; `exams/exam-questions.js` reads endpoints from `data-config` JSON.
Changing those routes/markup breaks the JS silently — grep both before edits.
