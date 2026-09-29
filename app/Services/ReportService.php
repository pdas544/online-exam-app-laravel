<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\Question;

class ReportService
{
    /**
     * R1: Result register — one row per session.
     *
     * Each row: session/student, attempt number for that student, status,
     * end_reason (null → unrecorded for pre-migration rows), marks, %,
     * pass/fail, violations, time.
     *
     * @return array<int, array{session_id:int, student_name:string, student_email:string, attempt:int, status:string, end_reason:string|null, end_reason_display:string, marks_secured:float, total_marks:float, percentage:float|null, passed:bool|null, is_graded:bool, violation_count:int, time_spent:int}>
     */
    public function resultRegister(Exam $exam): array
    {
        $sessions = ExamSession::with(['student', 'answers'])
            ->where('exam_id', $exam->id)
            ->orderBy('student_id')
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();

        // Attempt counter per student
        $attemptCounters = [];
        $rows = [];

        foreach ($sessions as $session) {
            $sid = $session->student_id;
            $attemptCounters[$sid] = ($attemptCounters[$sid] ?? 0) + 1;

            $earned = (float) $session->answers->sum('points_earned');
            $total = (float) $session->answers->sum('max_points');
            $percentage = $session->score !== null ? (float) $session->score : null;

            // Pass/fail only meaningful when graded and completed
            $passed = $session->passed;
            if ($session->score === null) {
                $passed = null;
            }

            $rows[] = [
                'session_id' => $session->id,
                'student_name' => $session->student->name ?? 'N/A',
                'student_email' => $session->student->email ?? 'N/A',
                'attempt' => $attemptCounters[$sid],
                'status' => $session->status,
                'end_reason' => $session->end_reason,
                'end_reason_display' => $this->endReasonDisplay($session->status, $session->end_reason),
                'marks_secured' => $earned,
                'total_marks' => $total,
                'percentage' => $percentage,
                'passed' => $passed !== null ? (bool) $passed : null,
                'is_graded' => $session->score !== null,
                'violation_count' => (int) $session->violation_count,
                'time_spent' => (int) $session->time_spent,
            ];
        }

        return $rows;
    }

    /**
     * R2: Summary aggregates over sessions for the exam.
     *
     * @return array{appeared:int, completed:int, terminated:int, expired:int, passed:int, failed:int, ungraded:int, mean:float|null, median:float|null, min:float|null, max:float|null, pass_rate:float|null}
     */
    public function summary(Exam $exam): array
    {
        $sessions = ExamSession::where('exam_id', $exam->id)->get();

        $appeared = $sessions->count();
        $completed = $sessions->where('status', 'completed')->count();
        $terminated = $sessions->where('status', 'terminated')->count();
        $expired = $sessions->where('status', 'expired')->count();

        $graded = $sessions->filter(fn (ExamSession $s) => $s->status === 'completed' && $s->score !== null);
        $ungraded = $sessions->filter(fn (ExamSession $s) => $s->status === 'completed' && $s->score === null)->count();

        $passed = $graded->where('passed', true)->count();
        $failed = $graded->where('passed', false)->count();

        $scores = $graded->pluck('score')->map(fn ($v) => (float) $v)->sort()->values();

        $mean = null;
        $median = null;
        $min = null;
        $max = null;
        $passRate = null;

        if ($scores->isNotEmpty()) {
            $mean = round($scores->avg(), 2);
            $min = round($scores->min(), 2);
            $max = round($scores->max(), 2);
            $count = $scores->count();
            if ($count % 2 === 1) {
                $median = round($scores->get(intdiv($count, 2)), 2);
            } else {
                $median = round(($scores->get($count / 2 - 1) + $scores->get($count / 2)) / 2, 2);
            }
            $passRate = $graded->count() > 0 ? round(($passed / $graded->count()) * 100, 2) : null;
        }

        return [
            'appeared' => $appeared,
            'completed' => $completed,
            'terminated' => $terminated,
            'expired' => $expired,
            'passed' => $passed,
            'failed' => $failed,
            'ungraded' => $ungraded,
            'mean' => $mean,
            'median' => $median,
            'min' => $min,
            'max' => $max,
            'pass_rate' => $passRate,
        ];
    }

    /**
     * R3: Per-question analysis.
     *
     * @return array<int, array{question_id:int, question_text:string, question_type:string, total_sessions:int, attempted:int, attempt_rate:float, correct:int, correct_rate:float|null, avg_time:float|null, distractor_counts:array<string,int>}>
     */
    public function questionAnalysis(Exam $exam): array
    {
        $exam->loadMissing(['questions']);
        $questions = $exam->questions;
        $totalSessions = ExamSession::where('exam_id', $exam->id)->count();

        $rows = [];
        foreach ($questions as $question) {
            $answers = \App\Models\StudentAnswer::where('exam_id', $exam->id)
                ->where('question_id', $question->id)
                ->get();

            $attempted = $answers->where('is_answered', true)->count();
            $attemptRate = $totalSessions > 0 ? round(($attempted / $totalSessions) * 100, 2) : 0.0;

            $correct = $answers->where('is_answered', true)->where('is_correct', true)->count();
            $correctRate = $attempted > 0 ? round(($correct / $attempted) * 100, 2) : null;

            $avgTime = null;
            $timed = $answers->whereNotNull('time_spent')->where('time_spent', '>', 0);
            if ($timed->isNotEmpty()) {
                $avgTime = round($timed->avg('time_spent'), 2);
            }

            $distractors = [];
            if (in_array($question->question_type, ['mcq_single', 'mcq_multiple', 'true_false'], true)) {
                // Count each selected option token among answered rows.
                foreach ($answers->where('is_answered', true) as $ans) {
                    $tokens = $this->normalizeAnswerTokens($ans->answer);
                    foreach ($tokens as $token) {
                        $distractors[$token] = ($distractors[$token] ?? 0) + 1;
                    }
                }
                ksort($distractors);
            }

            $rows[] = [
                'question_id' => $question->id,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'total_sessions' => $totalSessions,
                'attempted' => $attempted,
                'attempt_rate' => $attemptRate,
                'correct' => $correct,
                'correct_rate' => $correctRate,
                'avg_time' => $avgTime,
                'distractor_counts' => $distractors,
            ];
        }

        return $rows;
    }

    /**
     * R4: Student card — per-answer detail for a single session.
     * Keeps Not-attempted distinct from Wrong via is_answered.
     *
     * @return array{summary: array, rows: array<int, array>, grading_pending: bool}
     */
    public function studentCard(ExamSession $session): array
    {
        $session->loadMissing(['exam.subject', 'answers.question']);

        $rows = $session->answers
            ->sortBy('question_id')
            ->values()
            ->map(function ($answer, $index) {
                $question = $answer->question;
                $status = 'not_attempted';
                if ($answer->is_answered) {
                    $status = $answer->is_correct ? 'correct' : 'wrong';
                }

                return [
                    'index' => $index + 1,
                    'question_id' => $answer->question_id,
                    'question_text' => $question->question_text ?? 'N/A',
                    'question_type' => $question->question_type ?? 'unknown',
                    'is_answered' => (bool) $answer->is_answered,
                    'status' => $status,
                    'is_correct' => $answer->is_correct !== null ? (bool) $answer->is_correct : null,
                    'answer' => $answer->answer,
                    'points_earned' => $answer->points_earned !== null ? (float) $answer->points_earned : null, // @phpstan-ignore notIdentical.alwaysTrue
                    'max_points' => (float) $answer->max_points,
                    'time_spent' => $answer->time_spent !== null ? (int) $answer->time_spent : null, // @phpstan-ignore notIdentical.alwaysTrue
                ];
            })->toArray();

        $earned = (float) $session->answers->sum('points_earned');
        $possible = (float) $session->answers->sum('max_points');

        $summary = [
            'session_id' => $session->id,
            'exam_name' => $session->exam->title ?? 'N/A',
            'subject' => $session->exam->subject->name ?? 'N/A',
            'student_name' => $session->student->name ?? 'N/A',
            'status' => $session->status,
            'end_reason' => $session->end_reason,
            'end_reason_display' => $this->endReasonDisplay($session->status, $session->end_reason),
            'submitted_at' => optional($session->submitted_at)->format('M d, Y h:i A'),
            'marks_secured' => $earned,
            'total_marks' => $possible,
            'percentage' => $session->score !== null ? (float) $session->score : null,
            'passed' => $session->passed !== null ? (bool) $session->passed : null,
            'violation_count' => (int) $session->violation_count,
            'time_spent' => (int) $session->time_spent,
        ];

        return [
            'summary' => $summary,
            'rows' => $rows,
            'grading_pending' => $session->score === null && $session->status === 'completed',
        ];
    }

    public function endReasonDisplay(string $status, ?string $endReason): string
    {
        if ($endReason === 'force_ended') {
            return 'Terminated (teacher)';
        }
        if ($endReason === 'auto_terminated') {
            return 'Terminated (auto — violations)';
        }
        if ($endReason === 'expired') {
            return 'Expired (time limit)';
        }
        if ($endReason !== null) {
            return ucfirst(str_replace('_', ' ', $endReason));
        }

        // No stored reason — explicit bucket per plan.
        if (in_array($status, ['terminated', 'expired'], true)) {
            return 'Aborted (unrecorded)';
        }

        return ucfirst($status);
    }

    /**
     * Normalize answer JSON to token list.
     *
     * @return array<int,string>
     */
    private function normalizeAnswerTokens(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $raw), fn ($v) => $v !== ''));
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $decoded), fn ($v) => $v !== ''));
            }

            return [trim($raw)];
        }

        return [trim((string) $raw)];
    }
}
