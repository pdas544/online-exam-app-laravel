@extends('layouts.app')

@section('title', 'Reports')

@section('content')
<div class="container py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Exam Reports</h1>
            <p class="text-muted mb-0">Offline-equivalent registers, summaries and question analysis.</p>
        </div>
        <a href="{{ route('teacher.dashboard') }}" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if($exams->count() > 0)
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Exam</th>
                                <th>Subject</th>
                                <th>Sessions</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($exams as $exam)
                                <tr>
                                    <td><strong>{{ $exam->title }}</strong><br><small class="text-muted">{{ $exam->status }}</small></td>
                                    <td>{{ $exam->subject->name ?? 'N/A' }}</td>
                                    <td><span class="badge bg-info">{{ $exam->sessions_count }}</span></td>
                                    <td class="text-end">
                                        <a href="{{ route('teacher.reports.show', $exam) }}" class="btn btn-sm btn-primary">View Reports</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3">
                    {{ $exams->links() }}
                </div>
            @else
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
                    <p>No exams found.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
