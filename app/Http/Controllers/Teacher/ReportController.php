<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(Request $request)
    {
        $user = Auth::user();

        $query = Exam::with(['subject'])
            ->withCount(['sessions'])
            ->orderBy('updated_at', 'desc');

        if (! $user->isAdmin()) {
            $query->where('teacher_id', $user->id);
        }

        $exams = $query->paginate(15)->withQueryString();

        return view('dashboard.teacher.reports.index', compact('exams'));
    }

    public function show(Exam $exam)
    {
        $this->authorize('view', $exam);

        $register = $this->reports->resultRegister($exam);
        $summary = $this->reports->summary($exam);
        $questions = $this->reports->questionAnalysis($exam);

        // Detect backfill approximation: any terminated without end_reason?
        $hasApproximate = ExamSession::where('exam_id', $exam->id)
            ->where('status', 'terminated')
            ->whereNull('end_reason')
            ->exists();

        return view('dashboard.teacher.reports.show', compact('exam', 'register', 'summary', 'questions', 'hasApproximate'));
    }

    public function studentCard(Exam $exam, ExamSession $session)
    {
        $this->authorize('view', $exam);

        if ((int) $session->exam_id !== (int) $exam->id) {
            abort(404);
        }

        $card = $this->reports->studentCard($session->fresh()->loadMissing(['student', 'exam.subject']));

        return view('dashboard.teacher.reports.student', compact('exam', 'session', 'card'));
    }

    // ---- CSV ----

    public function csvRegister(Exam $exam): StreamedResponse
    {
        $this->authorize('view', $exam);
        $rows = $this->reports->resultRegister($exam);
        $filename = $this->csvFilename($exam, 'register');

        return $this->csvResponse($filename, ['Student', 'Email', 'Attempt', 'Status', 'End Reason', 'Marks', 'Total', 'Percentage', 'Result', 'Violations', 'Time (s)'], function () use ($rows) {
            $out = fopen('php://output', 'w');
            foreach ($rows as $r) {
                $result = $r['is_graded'] ? ($r['passed'] ? 'Pass' : 'Fail') : 'Pending';
                $pct = $r['percentage'] !== null ? number_format($r['percentage'], 2) : '';
                fputcsv($out, [
                    $r['student_name'], $r['student_email'], $r['attempt'], $r['status'], $r['end_reason_display'],
                    number_format($r['marks_secured'], 2), number_format($r['total_marks'], 2), $pct, $result,
                    $r['violation_count'], $r['time_spent'],
                ]);
            }
            fclose($out);
        });
    }

    public function csvQuestions(Exam $exam): StreamedResponse
    {
        $this->authorize('view', $exam);
        $rows = $this->reports->questionAnalysis($exam);
        $filename = $this->csvFilename($exam, 'questions');

        return $this->csvResponse($filename, ['Question', 'Type', 'Attempted', 'Total Sessions', 'Attempt Rate %', 'Correct', 'Correct Rate %', 'Avg Time (s)', 'Distractors'], function () use ($rows) {
            $out = fopen('php://output', 'w');
            foreach ($rows as $r) {
                fputcsv($out, [
                    mb_strimwidth($r['question_text'], 0, 80, '...'),
                    $r['question_type'],
                    $r['attempted'],
                    $r['total_sessions'],
                    $r['attempt_rate'],
                    $r['correct'],
                    $r['correct_rate'] ?? '',
                    $r['avg_time'] ?? '',
                    json_encode($r['distractor_counts']),
                ]);
            }
            fclose($out);
        });
    }

    public function csvStudentCard(Exam $exam, ExamSession $session): StreamedResponse
    {
        $this->authorize('view', $exam);
        if ((int) $session->exam_id !== (int) $exam->id) {
            abort(404);
        }
        $card = $this->reports->studentCard($session);
        $filename = $this->csvFilename($exam, 'student-'.$session->id);

        return $this->csvResponse($filename, ['#', 'Question', 'Status', 'Points', 'Max', 'Time (s)'], function () use ($card) {
            $out = fopen('php://output', 'w');
            foreach ($card['rows'] as $row) {
                fputcsv($out, [
                    $row['index'],
                    mb_strimwidth($row['question_text'], 0, 80, '...'),
                    $row['status'],
                    $row['points_earned'] ?? '',
                    $row['max_points'],
                    $row['time_spent'] ?? '',
                ]);
            }
            fclose($out);
        });
    }

    // ---- PDF ----

    public function pdfRegister(Exam $exam)
    {
        $this->authorize('view', $exam);
        $register = $this->reports->resultRegister($exam);
        $summary = $this->reports->summary($exam);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('dashboard.teacher.reports.pdf.register', compact('exam', 'register', 'summary'));
        $pdf->setPaper('a4', 'landscape');

        return $pdf->download($this->pdfFilename($exam, 'register'));
    }

    public function pdfQuestions(Exam $exam)
    {
        $this->authorize('view', $exam);
        $questions = $this->reports->questionAnalysis($exam);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('dashboard.teacher.reports.pdf.questions', compact('exam', 'questions'));
        $pdf->setPaper('a4', 'landscape');

        return $pdf->download($this->pdfFilename($exam, 'questions'));
    }

    public function pdfStudentCard(Exam $exam, ExamSession $session)
    {
        $this->authorize('view', $exam);
        if ((int) $session->exam_id !== (int) $exam->id) {
            abort(404);
        }
        $card = $this->reports->studentCard($session);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('dashboard.teacher.reports.pdf.student', compact('exam', 'session', 'card'));
        $pdf->setPaper('a4', 'portrait');

        return $pdf->download($this->pdfFilename($exam, 'student-'.$session->id));
    }

    public function pdfSummary(Exam $exam)
    {
        $this->authorize('view', $exam);
        $summary = $this->reports->summary($exam);
        $register = $this->reports->resultRegister($exam);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('dashboard.teacher.reports.pdf.summary', compact('exam', 'summary', 'register'));
        $pdf->setPaper('a4', 'portrait');

        return $pdf->download($this->pdfFilename($exam, 'summary'));
    }

    private function csvResponse(string $filename, array $header, callable $writer): StreamedResponse
    {
        return response()->stream(function () use ($header, $writer) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);
            fclose($out);
            $writer();
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function csvFilename(Exam $exam, string $suffix): string
    {
        $slug = \Illuminate\Support\Str::slug($exam->title) ?: 'exam-'.$exam->id;

        return $slug.'-'.$suffix.'-'.now()->format('Ymd').'.csv';
    }

    private function pdfFilename(Exam $exam, string $suffix): string
    {
        $slug = \Illuminate\Support\Str::slug($exam->title) ?: 'exam-'.$exam->id;

        return $slug.'-'.$suffix.'-'.now()->format('Ymd').'.pdf';
    }
}
