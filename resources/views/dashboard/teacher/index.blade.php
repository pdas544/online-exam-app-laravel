@extends('dashboard.layouts.base', [
    'role' => 'teacher',
    'title' => 'Teacher Dashboard',
    'stats' => $stats,
    'quickActions' => $quickActions,
    'recentActivity' => $recentActivity,
    'activityTitle' => 'My Recent Activity'
])

@section('dashboard-main')
    <!-- Upcoming Exams -->
    <div class="card mb-4">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="bi bi-calendar-event me-2"></i>Upcoming & Published Exams
            </h5>
            <a href="{{ route('exams.index') }}" class="btn btn-sm btn-primary">
                View All <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="card-body p-0">
            @if(count($upcomingExams) > 0)
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Exam Title</th>
                            <th>Subject</th>
                            <th>Questions</th>
                            <th>Total Marks</th>
                            <th>Duration</th>
                            <th>Available From</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($upcomingExams as $exam)
                            <tr>
                                <td>
                                    <strong>{{ $exam['title'] }}</strong>
                                </td>
                                <td>{{ $exam['subject'] }}</td>
                                <td>
                                    <span class="badge bg-info">{{ $exam['question_count'] }}</span>
                                </td>
                                <td>
                                    <span class="badge bg-dark">{{ $exam['total_marks'] }}</span>
                                </td>
                                <td>
                                    <i class="bi bi-clock me-1"></i>{{ $exam['time_limit'] }} min
                                </td>
                                <td>
                                    <small>{{ $exam['available_from'] }}</small>
                                </td>
                                <td>
                                    @if($exam['status'] == 'available')
                                        <span class="badge bg-success">Available</span>
                                    @else
                                        <span class="badge bg-warning">Upcoming</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="{{ route('exams.show', $exam['id']) }}"
                                           class="btn btn-info" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('exams.questions', $exam['id']) }}"
                                           class="btn btn-primary" title="Manage Questions">
                                            <i class="bi bi-list-check"></i>
                                        </a>
                                        @if($exam['status'] == 'available')
                                            <a href="{{ route('teacher.monitor.exam', $exam['id']) }}"
                                               class="btn btn-success" title="Monitor Live">
                                                <i class="bi bi-camera-video"></i>
                                            </a>
{{--                                            <button class="btn btn-success" title="Monitor Live">--}}
{{--                                                --}}
{{--                                            </button>--}}
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-4">
                    <i class="bi bi-calendar-x fs-1 text-muted d-block mb-2"></i>
                    <p class="text-muted">No upcoming exams scheduled.</p>
                    <a href="{{ route('exams.create') }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-plus-circle"></i> Create Your First Exam
                    </a>
                </div>
            @endif
        </div>
    </div>

    <!-- Subject Performance Overview -->
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-pie-chart me-2"></i>Subjects Overview
                    </h5>
                </div>
                <div class="card-body">
                    @if(count($subjectPerformance) > 0)
                        <canvas id="subjectChart" height="200"></canvas>

                        <div class="mt-4">
                            @foreach($subjectPerformance as $subject)
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between">
                                        <span>{{ $subject['name'] }}</span>
                                        <span>
                                            <span class="badge bg-info">{{ $subject['questions'] }} Q</span>
                                            <span class="badge bg-warning">{{ $subject['exams'] }} E</span>
                                        </span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-{{ $subject['color'] }}"
                                             style="width: {{ min(100, ($subject['questions'] + $subject['exams']) * 10) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-3">
                            <i class="bi bi-book fs-1 text-muted d-block mb-2"></i>
                            <p class="text-muted">No subjects created yet.</p>
                            <a href="{{ route('subjects.create') }}" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-circle"></i> Create Subject
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <!-- Quick Stats Cards -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <i class="bi bi-check-circle text-success fs-1 d-block mb-2"></i>
                            <h3>{{ $stats[3]['value'] }}</h3>
                            <small class="text-muted">Published Exams</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card bg-light">
                        <div class="card-body text-center">
                            <i class="bi bi-people text-danger fs-1 d-block mb-2"></i>
                            <h3>{{ $stats[4]['value'] }}</h3>
                            <small class="text-muted">Active Sessions</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tips & Reminders -->
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lightbulb me-2"></i>Teacher Tips
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            Create subjects before adding questions
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            You can override question points per exam
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            Set availability window for timed exams
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            Preview exams before publishing
                        </li>
                        <li class="mb-2">
                            <i class="bi bi-check-circle-fill text-success me-2"></i>
                            Monitor live sessions in real-time
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            @if(count($subjectPerformance) > 0)
            const ctx = document.getElementById('subjectChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: {!! json_encode(array_column($subjectPerformance, 'name')) !!},
                    datasets: [{
                        data: {!! json_encode(array_column($subjectPerformance, 'questions')) !!},
                        backgroundColor: [
                            '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                font: { size: 11 }
                            }
                        }
                    }
                }
            });
            @endif
        });
    </script>
@endpush
