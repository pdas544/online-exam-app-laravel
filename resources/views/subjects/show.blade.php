@extends('layouts.app')

@section('title', 'Subject Details')
@section('page-title', 'Subject: ' . $subject->name)


@section('content')
    <div class="d-flex justify-content-between align-items-center m-3">
        <div>
            <h1 class="h3">Subject Management</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin Dashboard</a></li>
                    <li class="breadcrumb-item active">Subjects</li>
                </ol>
            </nav>
        </div>
        @section('header-buttons-right')

            <div class="d-flex justify-content-end m-3 gap-1">
                <a href="{{ route('subjects.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Add Subject
                </a>

            <a href="{{ route('subjects.index') }}" class="btn btn-primary">
                <i class="bi bi-eye"></i> View All Subjects
            </a>
            </div>
        @endsection

    </div>
    <div class="row">
        <!-- Subject Details -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Subject Details</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless">
                        <tr>
                            <th width="40%">ID:</th>
                            <td>#{{ $subject->id }}</td>
                        </tr>
                        <tr>
                            <th>Name:</th>
                            <td><strong>{{ $subject->name }}</strong></td>
                        </tr>
                        <tr>
                            <th>Description:</th>
                            <td>{{ $subject->description ?? 'No description' }}</td>
                        </tr>
                        <tr>
                            <th>Created By:</th>
                            <td>{{ $subject->creator->name ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Created At:</th>
                            <td>{{ $subject->created_at->format('F d, Y \a\t h:i A') }}</td>
                        </tr>
                        <tr>
                            <th>Updated At:</th>
                            <td>{{ $subject->updated_at->format('F d, Y \a\t h:i A') }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <h3>{{ $subject->questions->count() }}</h3>
                            <small class="text-muted">Questions</small>
                        </div>
                        <div class="col-6">
                            <h3>{{ $subject->exams->count() }}</h3>
                            <small class="text-muted">Exams</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Questions in this Subject -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>Questions ({{ $subject->questions->count() }})</h5>
                </div>
                <div class="card-body">
                    @if($subject->questions->count() > 0)
                        <div class="list-group">
                            @foreach($subject->questions->take(10) as $question)
                                <a href="{{ route('questions.show', $question) }}"
                                   class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">{{ Str::limit($question->question_text, 80) }}</h6>
                                        <small class="badge
                                @if($question->isMultipleChoice()) badge-mcq
                                @elseif($question->isTrueFalse()) badge-tf
                                @else badge-fb @endif">
                                            {{ strtoupper(str_replace('_', ' ', $question->question_type)) }}
                                        </small>
                                    </div>
                                    <p class="mb-1">
                                        <small class="text-muted">
                                            Points: {{ $question->points }} |
                                            Created: {{ $question->created_at->diffForHumans() }}
                                        </small>
                                    </p>
                                </a>
                            @endforeach
                        </div>

                        @if($subject->questions->count() > 10)
                            <div class="text-center mt-3">
                                <a href="{{ route('questions.index') }}?subject_id={{ $subject->id }}"
                                   class="btn btn-sm btn-outline-primary">
                                    View All {{ $subject->questions->count() }} Questions
                                </a>
                            </div>
                        @endif

                    @else
                        <div class="text-center py-5">
                            <i class="fas fa-question-circle fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No questions in this subject yet.</p>
                            <a href="{{ route('questions.create') }}?subject_id={{ $subject->id }}"
                               class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add First Question
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
