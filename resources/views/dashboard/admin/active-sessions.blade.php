@extends('layouts.app')

@section('title', 'Active Exam Sessions')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1">Active Exam Sessions</h1>
                <p class="text-muted mb-0">Live overview of in-progress and paused exam attempts</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="{{ route('admin.exam-sessions.active') }}"
               class="btn btn-sm {{ empty($statusFilter) ? 'btn-primary' : 'btn-outline-primary' }} rounded-pill">
                All
            </a>
            <a href="{{ route('admin.exam-sessions.active', ['status' => 'in_progress']) }}"
               class="btn btn-sm {{ $statusFilter === 'in_progress' ? 'btn-success' : 'btn-outline-success' }} rounded-pill">
                In Progress ({{ $statusCounts['in_progress'] ?? 0 }})
            </a>
            <a href="{{ route('admin.exam-sessions.active', ['status' => 'paused']) }}"
               class="btn btn-sm {{ $statusFilter === 'paused' ? 'btn-warning' : 'btn-outline-warning' }} rounded-pill">
                Paused ({{ $statusCounts['paused'] ?? 0 }})
            </a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Exam</th>
                            <th>Student</th>
                            <th>Teacher</th>
                            <th>Status</th>
                            <th>Started</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($activeSessions as $session)
                            <tr>
                                <td>{{ $session->exam->title ?? 'N/A' }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $session->student->name ?? 'N/A' }}</div>
                                    <small class="text-muted">{{ $session->student->email ?? 'N/A' }}</small>
                                </td>
                                <td>{{ $session->teacher->name ?? 'N/A' }}</td>
                                <td>
                                    <span class="badge bg-{{ $session->status === 'in_progress' ? 'success' : 'warning' }}">
                                        {{ ucfirst(str_replace('_', ' ', $session->status)) }}
                                    </span>
                                </td>
                                <td>{{ optional($session->started_at)->diffForHumans() ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No active exam sessions found.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if(method_exists($activeSessions, 'links'))
            <div class="mt-3">
                {{ $activeSessions->links() }}
            </div>
        @endif
    </div>
@endsection
