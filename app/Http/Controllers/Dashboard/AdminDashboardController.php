<?php

namespace App\Http\Controllers\Dashboard;

use App\Services\DashboardService;
use Illuminate\Http\Request;

class AdminDashboardController extends BaseDashboardController
{
    public function __construct(
        private DashboardService $dashboards,
    ) {}

    protected function checkAccess(): bool
    {
        return $this->user->isAdmin();
    }

    public function index()
    {
        $overview = $this->dashboards->adminOverview();

        return view('dashboard.admin.index', [
            'stats' => $overview['stats'],
            'quickActions' => $overview['quickActions'],
            'recentActivity' => $overview['recentActivity'],
        ]);
    }

    public function activeSessions(Request $request)
    {
        $result = $this->dashboards->activeSessions($request->query('status'));

        return view('dashboard.admin.active-sessions', [
            'activeSessions' => $result['sessions'],
            'statusFilter' => $request->query('status'),
            'statusCounts' => $result['counts'],
        ]);
    }
}
