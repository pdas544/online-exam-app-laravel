@extends('dashboard.layouts.base', [
    'role' => 'teacher',
    'title' => 'Live Monitoring'
])

@section('dashboard-main')
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-camera-video me-2"></i>Active Exams</h5>
                    </div>
                    <div class="card-body">
                        @if($activeExams->count() > 0)
                            <div class="row">
                                @foreach($activeExams as $exam)
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100 border-primary">
                                            <div class="card-body">
                                                <h5 class="card-title">{{ $exam->title }}</h5>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        <i class="bi bi-people me-1"></i>
                                                        {{ $exam->sessions_count }} active students
                                                    </small>
                                                </p>
                                            </div>
                                            <div class="card-footer bg-light">
                                                <a href="{{ route('teacher.monitor.exam', $exam) }}"
                                                   class="btn btn-primary btn-sm w-100">
                                                    <i class="bi bi-eye me-1"></i> Monitor Now
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-4">
                                <i class="bi bi-camera-video-off fs-1 text-muted d-block mb-3"></i>
                                <p class="text-muted">No active exams to monitor</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
