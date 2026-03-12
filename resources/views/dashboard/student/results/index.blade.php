@extends('layouts.app')

@section('title', 'My Results')

@section('content')
    <div class="container-fluid py-3">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1">My Results</h1>
                <p class="text-muted mb-0">Completed exam attempts</p>
            </div>
            <a href="{{ route('student.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                @if($results->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th>Exam Name</th>
                                <th>Marks Secured</th>
                                <th>Submitted At</th>
                                <th class="text-end">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($results as $result)
                                <tr>
                                    <td>{{ $result['exam_name'] }}</td>
                                    <td>{{ rtrim(rtrim(number_format($result['marks_secured'], 2, '.', ''), '0'), '.') }}/{{ rtrim(rtrim(number_format($result['total_marks'], 2, '.', ''), '0'), '.') }}</td>
                                    <td>{{ $result['submitted_at'] ?? '-' }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('student.results.show', $result['session_id']) }}" class="btn btn-outline-primary btn-sm">
                                            View Report
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-clipboard-x fs-1 d-block mb-2"></i>
                        No completed exam results yet.
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
