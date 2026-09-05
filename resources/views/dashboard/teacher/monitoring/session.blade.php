@extends('dashboard.layouts.base', [
    'role' => auth()->user()->role,
    'title' => 'Session Details'
])

@section('dashboard-main')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">{{ $session->student->name }}</h5>
                <small class="text-muted">{{ $session->student->email }}</small>
            </div>
            <a href="{{ route('monitor.exam', $session->exam_id) }}" class="btn btn-outline-secondary btn-sm">
                Back to Monitor
            </a>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="text-muted">Exam</div>
                    <div class="fw-bold">{{ $session->exam->title }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted">Status</div>
                    <span class="badge bg-{{ $session->status === 'in_progress' ? 'success' : ($session->status === 'paused' ? 'warning' : 'secondary') }}">
                        {{ ucfirst(str_replace('_', ' ', $session->status)) }}
                    </span>
                </div>
                <div class="col-md-4">
                    <div class="text-muted">Violations</div>
                    <div class="fw-bold">{{ $session->violation_count }}</div>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="text-muted">Time Spent</div>
                    <div class="fw-bold">{{ floor($session->time_spent / 60) }} min</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted">Last Activity</div>
                    <div class="fw-bold">{{ $session->last_activity_at?->diffForHumans() ?? 'Just now' }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted">Progress</div>
                    @php
                        $answered = $session->answers()->where('is_answered', true)->count();
                        $total = $session->total_questions;
                    @endphp
                    <div class="fw-bold">{{ $answered }}/{{ $total }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
