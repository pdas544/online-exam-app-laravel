@extends('dashboard.layouts.base', [
    'role' => 'admin',
    'title' => 'Administrator Dashboard',
    'stats' => $stats,
    'quickActions' => $quickActions,
    'recentActivity' => $recentActivity,
    'showRecentActivity' => false
])

@section('dashboard-main')
    <div class="row g-2 g-md-3 align-items-stretch mb-3">
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
                        View All <i class="bi bi-arrow-right ms-1"></i>
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
                        View All Sessions <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-2 g-md-3 align-items-stretch mb-3">
        <div class="col-12 col-md-4 d-flex">
            <div class="card border-0 shadow-sm w-100 h-100">
                <div class="card-body p-3 p-md-4">
                    <p class="text-muted text-uppercase small fw-semibold mb-2">Sessions by Status</p>
                    @forelse(($health['sessions_by_status'] ?? []) as $status => $count)
                        <span class="badge bg-secondary me-1 mb-1">{{ $status }}: {{ $count }}</span>
                    @empty
                        <p class="text-muted mb-0">No sessions yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4 d-flex">
            <div class="card border-0 shadow-sm w-100 h-100">
                <div class="card-body p-3 p-md-4">
                    <p class="text-muted text-uppercase small fw-semibold mb-2">Queue Health</p>
                    <h2 class="mb-1">{{ $health['queue_depth'] ?? 0 }}</h2>
                    <p class="mb-1 {{ ($health['failed_jobs'] ?? 0) > 0 ? 'text-danger fw-semibold' : 'text-muted' }}">
                        Failed jobs: {{ $health['failed_jobs'] ?? 0 }}
                    </p>
                    <p class="text-muted mb-0">Pending + failed background jobs</p>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-4 d-flex">
            <div class="card border-0 shadow-sm w-100 h-100">
                <div class="card-body p-3 p-md-4">
                    <p class="text-muted text-uppercase small fw-semibold mb-2">Proctoring (last hour)</p>
                    <h2 class="mb-1">{{ $health['violations_last_hour'] ?? 0 }}</h2>
                    <p class="{{ ($health['ungraded_completions'] ?? 0) > 0 ? 'text-warning fw-semibold' : 'text-muted' }} mb-0">
                        Ungraded completions: {{ $health['ungraded_completions'] ?? 0 }}
                    </p>
                </div>
            </div>
        </div>
    </div>


@endsection
