@extends('layouts.app')

@section('title', 'Student Card')

@section('content')
<div class="container py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1">Student Card</h1>
            <p class="text-muted mb-0">{{ $exam->title }} · {{ $card['summary']['student_name'] }} · Session #{{ $session->id }}</p>
            <p class="small text-muted mb-0">Status: <span class="badge bg-secondary">{{ $card['summary']['end_reason_display'] }}</span>
                @if($card['grading_pending']) <span class="badge bg-warning">Grading pending</span> @endif
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('teacher.reports.show', $exam) }}" class="btn btn-outline-secondary btn-sm">Back to Register</a>
            <a href="{{ route('teacher.reports.csv.student', [$exam, $session]) }}" class="btn btn-outline-primary btn-sm">CSV</a>
            <a href="{{ route('teacher.reports.pdf.student', [$exam, $session]) }}" class="btn btn-outline-danger btn-sm">PDF</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 text-center">
                <div class="col-3"><div class="border rounded p-2"><div class="small text-muted">Marks</div><strong>{{ number_format($card['summary']['marks_secured'],2) }}/{{ number_format($card['summary']['total_marks'],2) }}</strong></div></div>
                <div class="col-3"><div class="border rounded p-2"><div class="small text-muted">Percentage</div><strong>{{ $card['summary']['percentage'] !== null ? number_format($card['summary']['percentage'],2).'%' : '—' }}</strong></div></div>
                <div class="col-3"><div class="border rounded p-2"><div class="small text-muted">Result</div>
                    @if($card['summary']['percentage'] === null) <span class="badge bg-warning">Pending</span>
                    @elseif($card['summary']['passed']) <span class="badge bg-success">Pass</span>
                    @else <span class="badge bg-danger">Fail</span>
                    @endif
                </div></div>
                <div class="col-3"><div class="border rounded p-2"><div class="small text-muted">Time / Violations</div><strong>{{ $card['summary']['time_spent'] }}s / {{ $card['summary']['violation_count'] }}</strong></div></div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Question</th>
                            <th>Status</th>
                            <th>Points</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($card['rows'] as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                <td style="max-width:360px;">{{ \Illuminate\Support\Str::limit($row['question_text'], 90) }}<br><small class="text-muted">{{ $row['question_type'] }}</small></td>
                                <td>
                                    @if($row['status'] === 'correct') <span class="badge bg-success">Correct</span>
                                    @elseif($row['status'] === 'wrong') <span class="badge bg-danger">Wrong</span>
                                    @else <span class="badge bg-secondary">Not attempted</span>
                                    @endif
                                </td>
                                <td>{{ $row['points_earned'] !== null ? number_format($row['points_earned'],2) : '—' }}/{{ number_format($row['max_points'],2) }}</td>
                                <td>{{ $row['time_spent'] !== null ? $row['time_spent'].'s' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
