@extends('dashboard.layouts.base', [
    'role' => 'student',
    'title' => 'Student Dashboard',
    'stats' => $stats,
    'quickActions' => $quickActions,
    'recentActivity' => $recentActivity,
    'activityTitle' => 'My Activity'
])

@section('dashboard-main')
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
    <!-- Resume Exams Section -->
    @if(count($resumeExams) > 0)
        <div class="card mb-4" id="resume-exams">
            <div class="card-header bg-warning text-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-play-circle me-2"></i>Resume Exams
                </h5>
                <span class="badge bg-light text-dark">{{ count($resumeExams) }} In Progress</span>
            </div>
            <div class="card-body">
                <div class="row">
                    @foreach($resumeExams as $exam)
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-warning">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">{{ $exam['title'] }}</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-2">
                                        <div class="col-6">
                                            <small class="text-muted d-block">
                                                <i class="bi bi-book me-1"></i>{{ $exam['subject'] }}
                                            </small>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">
                                                <i class="bi bi-person me-1"></i>{{ $exam['teacher'] }}
                                            </small>
                                        </div>
                                    </div>

                                    <!-- Progress Bar -->
                                    <div class="mb-2">
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">Progress</small>
                                            <small class="text-muted">{{ $exam['progress'] }} ({{ $exam['percentage'] }}%)</small>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-warning"
                                                 style="width: {{ $exam['percentage'] }}%"></div>
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <div class="col-4">
                                            <small class="text-muted">
                                                <i class="bi bi-clock me-1"></i>{{ $exam['duration'] }} min
                                            </small>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted">
                                                <i class="bi bi-hourglass me-1"></i>{{ floor($exam['time_spent'] / 60) }} min spent
                                            </small>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted">
                                                <i class="bi bi-star me-1"></i>{{ $exam['total_marks'] }} marks
                                            </small>
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mb-2">
                                        <i class="bi bi-calendar me-1"></i>
                                        Available until: {{ $exam['available_until'] }}
                                    </small>
                                </div>
                                <div class="card-footer bg-light">
                                    <a href="{{ route('exam.session.resume', $exam['session_id']) }}"
                                       class="btn btn-warning btn-sm w-100">
                                        <i class="bi bi-arrow-repeat me-2"></i>Resume Exam
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
    <!-- Available Exams Section -->
    <div class="card mb-4" id="available-exams">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="bi bi-calendar-check me-2"></i>Available Exams
            </h5>
            <span class="badge bg-light text-dark">{{ count($availableExams) }} Available</span>
        </div>
        <div class="card-body">
            @if(count($availableExams) > 0)
                <div class="row">
                    @foreach($availableExams as $exam)
                        <div class="col-md-6 mb-3">
                            <div class="card h-100 border-success">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">{{ $exam['title'] }}</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-2">
                                        <div class="col-6">
                                            <small class="text-muted d-block">
                                                <i class="bi bi-book me-1"></i>{{ $exam['subject'] }}
                                            </small>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">
                                                <i class="bi bi-person me-1"></i>{{ $exam['teacher'] }}
                                            </small>
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-4">
                                            <small class="text-muted">
                                                <i class="bi bi-clock me-1"></i>{{ $exam['duration'] }} min
                                            </small>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted">
                                                <i class="bi bi-question-circle me-1"></i>{{ $exam['questions_count'] }} Q
                                            </small>
                                        </div>
                                        <div class="col-4">
                                            <small class="text-muted">
                                                <i class="bi bi-star me-1"></i>{{ $exam['total_marks'] }} marks
                                            </small>
                                        </div>
                                    </div>
                                    <small class="text-muted d-block mb-2">
                                        <i class="bi bi-calendar me-1"></i>
                                        Available until: {{ $exam['available_until'] }}
                                    </small>
                                </div>
                                <div class="card-footer bg-light">
                                    <a href="{{route('exam.start',$exam['id'])}}" class="btn btn-success btn-sm w-100">
                                        <i class="bi bi-play-circle me-2"></i>Start Exam
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if(count($availableExams) > 4)
                    <div class="text-center mt-2">
                        <a href="#" class="btn btn-outline-success btn-sm">
                            View All Available Exams <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                @endif
            @else
                <div class="text-center py-4">
                    <i class="bi bi-calendar-x fs-1 text-muted d-block mb-2"></i>
                    <p class="text-muted">No exams available at the moment.</p>
                </div>
            @endif
        </div>
    </div>

    <div class="row">
        <!-- Upcoming Exams -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-warning text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-calendar-event me-2"></i>Upcoming Exams
                    </h5>
                </div>
                <div class="card-body p-0">
                    @if(count($upcomingExams) > 0)
                        <div class="list-group list-group-flush">
                            @foreach($upcomingExams as $exam)
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">{{ $exam['title'] }}</h6>
                                        <small class="text-warning">{{ $exam['duration'] }} min</small>
                                    </div>
                                    <p class="mb-1">
                                        <i class="bi bi-book me-1"></i>{{ $exam['subject'] }}
                                    </p>
                                    <small class="text-muted">
                                        <i class="bi bi-clock me-1"></i>
                                        Starts: {{ $exam['available_from'] }}
                                    </small>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-calendar fs-1 text-muted d-block mb-2"></i>
                            <p class="text-muted">No upcoming exams scheduled.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Past Results -->
        <div class="col-md-6" id="results">
            <div class="card mb-4">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-bar-chart me-2"></i>Recent Results
                    </h5>
                    <a href="#" class="btn btn-light btn-sm">View All</a>
                </div>
                <div class="card-body p-0">
                    @if(count($pastResults) > 0)
                        <div class="list-group list-group-flush">
                            @foreach($pastResults as $result)
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">{{ $result['exam_title'] }}</h6>
                                            <small class="text-muted">{{ $result['subject'] }}</small>
                                        </div>
                                        <div class="text-end">
                                            <h5 class="mb-0 {{ $result['status'] == 'passed' ? 'text-success' : 'text-danger' }}">
                                                {{ round($result['score']) }}%
                                            </h5>
                                            <small class="text-muted">
                                                {{ $result['marks_obtained'] }}/{{ $result['total_marks'] }}
                                            </small>
                                        </div>
                                    </div>
                                    <div class="progress mt-2" style="height: 5px;">
                                        <div class="progress-bar bg-{{ $result['status'] == 'passed' ? 'success' : 'danger' }}"
                                             style="width: {{ $result['score'] }}%"></div>
                                    </div>
                                    <small class="text-muted d-block mt-1">
                                        <i class="bi bi-clock me-1"></i>{{ $result['submitted_at'] }}
                                    </small>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-bar-chart-steps fs-1 text-muted d-block mb-2"></i>
                            <p class="text-muted">No exam results yet.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Subject Explorer -->
    <div class="card" id="subjects">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">
                <i class="bi bi-grid-3x3-gap-fill me-2"></i>Available Subjects
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                @php
                    $subjects = \App\Models\Subject::withCount('questions')->take(6)->get();
                @endphp

                @foreach($subjects as $subject)
                    <div class="col-md-4 mb-3">
                        <div class="card h-100 border-primary">
                            <div class="card-body">
                                <h6 class="card-title">{{ $subject->name }}</h6>
                                <p class="card-text small text-muted">
                                    {{ $subject->description ?? 'No description available' }}
                                </p>
                                <div class="d-flex justify-content-between">
                                    <small>
                                        <i class="bi bi-question-circle me-1"></i>
                                        {{ $subject->questions_count }} questions
                                    </small>
                                    <small>
                                        <i class="bi bi-file-text me-1"></i>
                                        {{ $subject->exams_count ?? 0 }} exams
                                    </small>
                                </div>
                            </div>
                            <div class="card-footer bg-light">
                                <a href="#" class="btn btn-sm btn-outline-primary w-100">
                                    Browse Exams
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .card-header.bg-success, .card-header.bg-warning,
        .card-header.bg-info, .card-header.bg-primary {
            border-bottom: none;
        }
        .list-group-item {
            transition: background-color 0.2s;
        }
        .list-group-item:hover {
            background-color: #f8f9fa;
        }
        .progress {
            border-radius: 10px;
        }
    </style>
@endpush
