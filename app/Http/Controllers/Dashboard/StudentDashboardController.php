<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ExamSession;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;

class StudentDashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboards,
    ) {
        if (! Auth::user()->isStudent()) {
            abort(403, 'Unauthorized access. Student privileges required.');
        }
    }

    public function index()
    {
        $overview = $this->dashboards->studentOverview(Auth::id());

        return view('dashboard.student.index', [
            'resumeExams' => $overview['resumeExams'],
            'availableExams' => $overview['availableExams'],
        ]);
    }

    /**
     * Show completed exam results for the logged in student.
     */
    public function results()
    {
        $results = $this->dashboards->studentResults(Auth::id());

        return view('dashboard.student.results.index', compact('results'));
    }

    /**
     * Show detailed report for one completed session owned by the logged in student.
     */
    public function showResult(ExamSession $session)
    {
        if ($session->student_id !== Auth::id()) {
            abort(403, 'Unauthorized access.');
        }

        if ($session->status !== 'completed') {
            return redirect()->route('student.results.index')
                ->with('error', 'Result is available only after exam completion.');
        }

        $detail = $this->dashboards->studentResultDetail($session);

        return view('dashboard.student.results.show', [
            'summary' => $detail['summary'],
            'rows' => $detail['rows'],
        ]);
    }
}
