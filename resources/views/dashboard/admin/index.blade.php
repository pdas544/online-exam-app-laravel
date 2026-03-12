@extends('dashboard.layouts.base', [
    'role' => 'admin',
    'title' => 'Administrator Dashboard',
    'stats' => $stats,
    'quickActions' => $quickActions,
    'recentActivity' => $recentActivity,
    'showRecentActivity' => false
])

@section('dashboard-main')
    <div class="row g-2 g-md-3 align-items-stretch">
        <div class="col-12 col-sm-6 d-flex">
            <div class="card border-0 shadow-sm w-100 h-100">
                <div class="card-body p-3 p-md-4 d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted text-uppercase small fw-semibold mb-2">Active Exams</p>
                        <h2 class="mb-1">{{ $stats['active_exams'] ?? 0 }}</h2>
                        <p class="text-muted mb-0">Published exams available to students</p>
                    </div>
                    <span class="badge bg-success-subtle text-success-emphasis p-2">
                        <i class="bi bi-file-earmark-text fs-5"></i>
                    </span>
                </div>
                <div class="card-footer bg-white border-0 pt-0 pb-3 pb-md-4 px-3 px-md-4">
                    <a href="{{ route('exams.index', ['status' => 'published']) }}" class="text-decoration-none fw-semibold">
                        View Active Exams <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 d-flex">
            <div class="card border-0 shadow-sm w-100 h-100">
                <div class="card-body p-3 p-md-4 d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted text-uppercase small fw-semibold mb-2">Active Exam Sessions</p>
                        <h2 class="mb-1">{{ $stats['active_exam_sessions'] ?? 0 }}</h2>
                        <p class="text-muted mb-0">Ongoing and paused student sessions</p>
                    </div>
                    <span class="badge bg-info-subtle text-info-emphasis p-2">
                        <i class="bi bi-play-circle fs-5"></i>
                    </span>
                </div>
                <div class="card-footer bg-white border-0 pt-0 pb-3 pb-md-4 px-3 px-md-4">
                    <a href="{{ route('admin.exam-sessions.active') }}" class="text-decoration-none fw-semibold">
                        View Active Sessions <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection
