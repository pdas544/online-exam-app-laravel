<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamSession;
use App\Models\ViolationLog;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    public function show()
    {
        if (! auth()->check() || ! auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        return response()->json([
            'sessions_by_status' => ExamSession::selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'queue_depth' => DB::table('jobs')->count(),
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'violations_last_hour' => ViolationLog::where('created_at', '>=', now()->subHour())->count(),
            'live_exams' => Exam::where('status', 'published')
                ->where(function ($query) {
                    $query->whereNull('available_from')
                        ->orWhere('available_from', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('available_to')
                        ->orWhere('available_to', '>=', now());
                })
                ->count(),
        ]);
    }
}
