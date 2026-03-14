@extends('layouts.app')

@section('title', 'Teacher Dashboard')

@section('content')
    <div class="container-fluid py-3">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1">Teacher Dashboard</h1>
                <p class="text-muted mb-0">{{ now()->format('l, F j, Y') }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </button>
            </form>
        </div>

        <div class="row g-4">
            <div class="col-lg-9">
                <!-- Upcoming & Published Exams -->
                <div class="card border-0 shadow-sm" id="upcoming-exams">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-calendar-event me-2 text-success"></i>Upcoming & Published Exams
                        </h5>
                        <a href="{{ route('exams.index') }}" class="btn btn-sm btn-primary">
                            View All <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                    <div class="card-body p-0">
                        @if(count($upcomingExams) > 0)
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                    <tr>
                                        <th>Exam Title</th>
                                        <th>Subject</th>
                                        <th>Questions</th>
                                        <th>Total Marks</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($upcomingExams as $exam)
                                        <tr>
                                            <td><strong>{{ $exam['title'] }}</strong></td>
                                            <td>{{ $exam['subject'] }}</td>
                                            <td><span class="badge bg-info">{{ $exam['question_count'] }}</span></td>
                                            <td><span class="badge bg-dark">{{ $exam['total_marks'] }}</span></td>
                                            <td><i class="bi bi-clock me-1"></i>{{ $exam['time_limit'] }} min</td>
                                            <td>
                                                @if($exam['status'] == 'available')
                                                    <span class="badge bg-success">Available</span>
                                                @else
                                                    <span class="badge bg-warning">Upcoming</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('exams.show', $exam['id']) }}" class="btn btn-outline-info" title="View">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="{{ route('exams.questions', $exam['id']) }}" class="btn btn-outline-primary" title="Manage Questions">
                                                        <i class="bi bi-list-check"></i>
                                                    </a>
                                                    @if($exam['status'] == 'available')
                                                        <a href="{{ route('monitor.exam', $exam['id']) }}" class="btn btn-outline-success" title="Monitor Live">
                                                            <i class="bi bi-camera-video"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                                <p>No upcoming exams scheduled.</p>
                                <a href="{{ route('exams.create') }}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-circle me-1"></i>Create Your First Exam
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-3">
                <!-- Quick Actions -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0">Quick Actions</h6>
                    </div>
                    <div class="list-group list-group-flush">
                        <a href="{{ route('exams.index') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i class="bi bi-file-earmark-text text-primary"></i>
                            <span>My Exams</span>
                        </a>
                        <a href="{{ route('subjects.index') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i class="bi bi-book text-success"></i>
                            <span>My Subjects</span>
                        </a>
                        <a href="{{ route('questions.index') }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                            <i class="bi bi-question-circle text-info"></i>
                            <span>My Questions</span>
                        </a>
                    </div>
                </div>

                <!-- Teacher Tips -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="bi bi-lightbulb me-1"></i>Teacher Tips</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0 small">
                            <li class="mb-2">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                Create subjects before adding questions
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                Override question points per exam
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                Set availability windows for timed exams
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                Preview exams before publishing
                            </li>
                            <li>
                                <i class="bi bi-check-circle-fill text-success me-2"></i>
                                Monitor live sessions in real-time
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            @if(isset($subjectPerformance) && is_countable($subjectPerformance) && count($subjectPerformance) > 0)
            const chartElement = document.getElementById('subjectChart');
            if (!chartElement) {
                return;
            }

            const ctx = chartElement.getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: {!! json_encode(collect($subjectPerformance)->pluck('name')->values()->all()) !!},
                    datasets: [{
                        data: {!! json_encode(collect($subjectPerformance)->pluck('questions')->values()->all()) !!},
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
