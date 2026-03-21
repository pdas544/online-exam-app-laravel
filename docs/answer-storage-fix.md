# Answer Storage Fix

## Summary
The system had mixed JSON storage patterns for question correct answers and student responses, causing values like `["false"]` to appear in reports and introducing grading mismatches.

This implementation standardizes the contract and adds a repair command for legacy rows.

## Canonical Contract
- `questions.correct_answers`: array
- `questions.options`: associative array (MCQ only) or `null`
- `student_answers.answer`: `null` or array

Type mapping:
- `mcq_single` -> `["A"]`
- `mcq_multiple` -> `["A", "C"]`
- `true_false` -> `["true"]` or `["false"]`
- `fill_blank` -> `["photosynthesis"]`

## Changes Implemented
1. `QuestionController::processQuestionData()` now stores native arrays for JSON-cast fields (no `json_encode(...)`).
2. `ExamSessionController::normalizeAnswer()` now writes answers as `null|array` only.
3. `StudentAnswer::autoGrade()` now normalizes both student and correct answers defensively, including legacy double-encoded JSON strings.
4. `StudentDashboardController` result formatting now decodes legacy JSON-string payloads before display.
5. `resources/views/exams/take.blade.php` now restores selected values from normalized answer arrays for all question types.
6. Added artisan command `answers:normalize-storage` in `routes/console.php` for one-time data repair.

## Data Repair Command
- Dry run:
  - `php artisan answers:normalize-storage --dry-run`
- Apply:
  - `php artisan answers:normalize-storage`

The command repairs:
- `questions.correct_answers`
- `questions.options`
- `student_answers.answer`

It also updates `student_answers.is_answered` based on normalized values.

## Recommended Rollout
1. Run dry-run in local/staging and verify counts.
2. Run apply in staging.
3. Manually verify result pages with true/false, mcq single/multiple, and fill blank.
4. Run apply in production during low traffic.
5. Spot-check old sessions for display and correctness.
