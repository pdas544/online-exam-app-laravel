@extends('layouts.app')

@section('title', 'Report — ' . $exam->title)

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h1 class="h4 mb-1">{{ $exam->title }}</h1>
            <p class="text-muted mb-0">{{ $exam->subject->name ?? 'N/A' }} · {{ $exam->status }} · Passing: {{ $exam->passing_marks }}</p>
            @if($hasApproximate)
                <div class="alert alert-warning py-2 px-3 mt-2 mb-0 small">Some terminated sessions pre-date the end-reason column; their reason is shown as approximate (forced vs auto).</div>
            @endif
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('teacher.reports.index') }}" class="btn btn-outline-secondary btn-sm">All Reports</a>
            <div class="dropdown">
                <button class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown">CSV</button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="{{ route('teacher.reports.csv.register', $exam) }}">Register CSV</a></li>
                    <li><a class="dropdown-item" href="{{ route('teacher.reports.csv.questions', $exam) }}">Questions CSV</a></li>
                </ul>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-danger btn-sm dropdown-toggle" data-bs-toggle="dropdown">PDF</button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="{{ route('teacher.reports.pdf.register', $exam) }}">Register PDF</a></li>
                    <li><a class="dropdown-item" href="{{ route('teacher.reports.pdf.summary', $exam) }}">Summary PDF</a></li>
                    <li><a class="dropdown-item" href="{{ route('teacher.reports.pdf.questions', $exam) }}">Questions PDF</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- R2 Summary -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white"><h5 class="mb-0">Summary</h5></div>
        <div class="card-body">
            <div class="row g-3 text-center">
                <div class="col-6 col-md-2"><div class="border rounded p-2"><div class="small text-muted">Appeared</div><div class="h4 mb-0">{{ $summary['appeared'] }}</div></div></div>
                <div class="col-6 col-md-2"><div class="border rounded p-2"><div class="small text-muted">Completed</div><div class="h4 mb-0">{{ $summary['completed'] }}</div></div></div>
                <div class="col-6 col-md-2"><div class="border rounded p-2"><div class="small text-muted">Passed</div><div class="h4 mb-0 text-success">{{ $summary['passed'] }}</div></div></div>
                <div class="col-6 col-md-2"><div class="border rounded p-2"><div class="small text-muted">Failed</div><div class="h4 mb-0 text-danger">{{ $summary['failed'] }}</div></div></div>
                <div class="col-6 col-md-2"><div class="border rounded p-2"><div class="small text-muted">Ungraded</div><div class="h4 mb-0">{{ $summary['ungraded'] }}</div></div></div>
                <div class="col-6 col-md-2"><div class="border rounded p-2"><div class="small text-muted">Pass Rate</div><div class="h4 mb-0">{{ $summary['pass_rate'] !== null ? $summary['pass_rate'].'%' : '—' }}</div></div></div>
            </div>
            <div class="row g-3 text-center mt-1">
                <div class="col-3"><div class="small text-muted">Mean</div><strong>{{ $summary['mean'] ?? '—' }}</strong></div>
                <div class="col-3"><div class="small text-muted">Median</div><strong>{{ $summary['median'] ?? '—' }}</strong></div>
                <div class="col-3"><div class="small text-muted">Min</div><strong>{{ $summary['min'] ?? '—' }}</strong></div>
                <div class="col-3"><div class="small text-muted">Max</div><strong>{{ $summary['max'] ?? '—' }}</strong></div>
            </div>
            @if($summary['terminated'] || $summary['expired'])
                <p class="small text-muted mt-3 mb-0">Terminated: {{ $summary['terminated'] }} · Expired: {{ $summary['expired'] }} · Aborted sessions are counted in "appeared" but excluded from mean/median.</p>
            @endif
        </div>
    </div>

    <!-- R1 Register -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Result Register</h5>
            <a href="{{ route('teacher.reports.csv.register', $exam) }}" class="btn btn-sm btn-outline-primary">Download CSV</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Attempt</th>
                            <th>Status</th>
                            <th>Marks</th>
                            <th>%</th>
                            <th>Result</th>
                            <th>Violations</th>
                            <th>Time</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($register as $row)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td><strong>{{ $row['student_name'] }}</strong><br><small class="text-muted">{{ $row['student_email'] }}</small></td>
                                <td>{{ $row['attempt'] }}</td>
                                <td><span class="badge bg-secondary">{{ $row['end_reason_display'] }}</span></td>
                                <td>{{ number_format($row['marks_secured'], 2) }}/{{ number_format($row['total_marks'], 2) }}</td>
                                <td>{{ $row['percentage'] !== null ? number_format($row['percentage'], 2).'%' : '—' }}</td>
                                <td>
                                    @if(!$row['is_graded'])
                                        <span class="badge bg-warning">Pending</span>
                                    @elseif($row['passed'])
                                        <span class="badge bg-success">Pass</span>
                                    @else
                                        <span class="badge bg-danger">Fail</span>
                                    @endif
                                </td>
                                <td>{{ $row['violation_count'] }}</td>
                                <td>{{ $row['time_spent'] }}s</td>
                                <td><a href="{{ route('teacher.reports.student', [$exam, $row['session_id']]) }}" class="btn btn-sm btn-outline-info">Card</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-muted py-4">No sessions yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- R3 Question Analysis -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Question Analysis</h5>
            <a href="{{ route('teacher.reports.csv.questions', $exam) }}" class="btn btn-sm btn-outline-primary">Download CSV</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Question</th>
                            <th>Type</th>
                            <th>Attempt Rate</th>
                            <th>Correct Rate</th>
                            <th>Avg Time</th>
                            <th>Distractors</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($questions as $q)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td style="max-width:280px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="{{ $q['question_text'] }}">{{ \Illuminate\Support\Str::limit($q['question_text'], 70) }}</td>
                                <td><span class="badge bg-light text-dark">{{ $q['question_type'] }}</span></td>
                                <td>{{ $q['attempt_rate'] }}% <small class="text-muted">({{ $q['attempted'] }}/{{ $q['total_sessions'] }})</small></td>
                                <td>{{ $q['correct_rate'] !== null ? $q['correct_rate'].'%' : '—' }} <small class="text-muted">({{ $q['correct'] }}/{{ $q['attempted'] }})</small></td>
                                <td>{{ $q['avg_time'] !== null ? $q['avg_time'].'s' : '—' }}</td>
                                <td><small class="font-monospace">{{ json_encode($q['distractor_counts']) }}</small></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No questions.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
