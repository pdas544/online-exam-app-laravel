<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;

class MetricsController extends Controller
{
    public function __construct(private DashboardService $dashboards) {}

    public function show()
    {
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        return response()->json($this->dashboards->adminHealth());
    }
}
