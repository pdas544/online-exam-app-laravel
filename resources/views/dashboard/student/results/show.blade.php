@extends('layouts.app')

@section('title', 'Result Report')

@section('content')
    <div class="container-fluid py-3">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-1">Result Report</h1>
                <p class="text-muted mb-0">{{ $summary['exam_name'] }}</p>
            </div>
            <a href="{{ route('student.results.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to My Results
            </a>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <small class="text-muted d-block">Subject</small>
                        <strong>{{ $summary['subject'] }}</strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Submitted At</small>
                        <strong>{{ $summary['submitted_at'] ?? '-' }}</strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Marks Secured</small>
                        <strong>{{ rtrim(rtrim(number_format($summary['marks_secured'], 2, '.', ''), '0'), '.') }}/{{ rtrim(rtrim(number_format($summary['total_marks'], 2, '.', ''), '0'), '.') }}</strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Questions</small>
                        <strong>{{ $rows->count() }}</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th style="width: 4rem;">#</th>
                            <th>Question</th>
                            <th>Correct Option</th>
                            <th>Option Chosen</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['index'] }}</td>
                                <td>{{ $row['question_text'] }}</td>
                                <td>{{ $row['correct_option'] }}</td>
                                <td>{{ $row['selected_option'] }}</td>
                                <td>
                                    @if($row['is_correct'])
                                        <span class="badge text-bg-success">Correct</span>
                                    @else
                                        <span class="badge text-bg-danger">Incorrect</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No answers available.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
