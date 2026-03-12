@extends('layouts.app')

@section('title', 'Student Dashboard')

@section('content')
    <div class="container-fluid py-3">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1">Student Dashboard</h1>
                <p class="text-muted mb-0">{{ now()->format('l, F j, Y') }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </button>
            </form>
        </div>

        @if(request()->query('ended'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                Your exam was ended by the Admin.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="row g-4">
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm mb-4" id="available-exams">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-calendar-check me-2 text-success"></i>Available Exams
                        </h5>
                        <span class="badge text-bg-light">{{ count($availableExams) }}</span>
                    </div>
                    <div class="card-body">
                        @if(count($availableExams) > 0)
                            <div class="row">
                                @foreach($availableExams as $exam)
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100 border">
                                            <div class="card-body">
                                                <h6 class="mb-2">{{ $exam['title'] }}</h6>
                                                <p class="small text-muted mb-2">
                                                    <i class="bi bi-book me-1"></i>{{ $exam['subject'] }}
                                                </p>
                                                <div class="d-flex flex-wrap gap-2 small text-muted mb-3">
                                                    <span><i class="bi bi-clock me-1"></i>{{ $exam['duration'] }} min</span>
                                                    <span><i class="bi bi-question-circle me-1"></i>{{ $exam['questions_count'] }} Q</span>
                                                    <span><i class="bi bi-star me-1"></i>{{ $exam['total_marks'] }} marks</span>
                                                </div>
                                                <small class="text-muted d-block mb-3">
                                                    <i class="bi bi-calendar me-1"></i>
                                                    Available until: {{ $exam['available_until'] }}
                                                </small>
                                                <a href="{{ route('exam.start', $exam['id']) }}" class="btn btn-success btn-sm w-100">
                                                    <i class="bi bi-play-circle me-1"></i>Start Exam
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                                No exams available at the moment.
                            </div>
                        @endif
                    </div>
                </div>

                @if(count($resumeExams) > 0)
                    <div class="card border-0 shadow-sm" id="resume-exams">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-play-circle me-2 text-warning"></i>Resume Exams
                            </h5>
                            <span class="badge text-bg-light">{{ count($resumeExams) }}</span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                @foreach($resumeExams as $exam)
                                    <div class="col-md-6 mb-3">
                                        <div class="card h-100 border-warning-subtle">
                                            <div class="card-body">
                                                <h6 class="mb-2">{{ $exam['title'] }}</h6>
                                                <p class="small text-muted mb-2">
                                                    <i class="bi bi-book me-1"></i>{{ $exam['subject'] }}
                                                </p>
                                                <div class="d-flex justify-content-between small mb-1">
                                                    <span class="text-muted">Progress</span>
                                                    <span class="text-muted">{{ $exam['progress'] }}</span>
                                                </div>
                                                <div class="progress mb-3" style="height: 7px;">
                                                    <div class="progress-bar bg-warning" style="width: {{ $exam['percentage'] }}%"></div>
                                                </div>
                                                <a href="{{ route('exam.session.resume', $exam['session_id']) }}" class="btn btn-outline-warning btn-sm w-100">
                                                    <i class="bi bi-arrow-repeat me-1"></i>Resume Exam
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-3">
                <div class="card border-0 shadow-sm sticky-lg-top" style="top: 1rem;">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">Quick Actions</h6>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="{{ route('student.results.index') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i class="bi bi-clipboard-data text-primary"></i>
                            <span>My Results</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
