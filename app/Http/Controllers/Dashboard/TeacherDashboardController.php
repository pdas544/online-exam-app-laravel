<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;

class TeacherDashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboards,
    ) {
        if (! Auth::user()->isTeacher()) {
            abort(403, 'Unauthorized access. Teacher privileges required.');
        }
    }

    public function index()
    {
        $overview = $this->dashboards->teacherOverview(Auth::id());

        return view('dashboard.teacher.index', [
            'upcomingExams' => $overview['upcomingExams'],
        ]);
    }
}
