@extends('layouts.app')

@section('title', 'Questions Bank')
@section('page-title', 'Questions Bank')


@section('content')
    <div class="d-flex justify-content-between align-items-center m-3">
        <div>
            <h1 class="h3">Subject Management</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin Dashboard</a></li>
                    <li class="breadcrumb-item active">Questions</li>
                </ol>
            </nav>
        </div>


        @section('header-buttons-right')
            <div class="d-flex justify-content-end m-3">
                <a href="{{ route('questions.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Add a Question
                </a>
            </div>
        @endsection


    </div>
    <div class="card">
        <div class="card-body">
            <!-- Filters -->
            <div class="row mb-4">
                <div class="col-md-8">
                    <form method="GET" action="{{ route('questions.index') }}" class="row g-3">
                        <div class="col-md-4">
                            <select name="subject_id" class="form-select" onchange="this.form.submit()">
                                <option value="">All Subjects</option>
                                @foreach($subjects as $subject)
                                    <option value="{{ $subject->id }}"
                                        {{ request('subject_id') == $subject->id ? 'selected' : '' }}>
                                        {{ $subject->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <select name="question_type" class="form-select" onchange="this.form.submit()">
                                <option value="">All Types</option>
                                <option value="multiple_choice_single"
                                    {{ request('question_type') == 'multiple_choice_single' ? 'selected' : '' }}>
                                    Multiple Choice (Single)
                                </option>
                                <option value="multiple_choice_multiple"
                                    {{ request('question_type') == 'multiple_choice_multiple' ? 'selected' : '' }}>
                                    Multiple Choice (Multiple)
                                </option>
                                <option value="true_false"
                                    {{ request('question_type') == 'true_false' ? 'selected' : '' }}>
                                    True/False
                                </option>
                                <option value="fill_blank"
                                    {{ request('question_type') == 'fill_blank' ? 'selected' : '' }}>
                                    Fill in Blank
                                </option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control"
                                       placeholder="Search questions..." value="{{ request('search') }}">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Questions Table -->
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Question</th>
                        <th>Type</th>
                        <th>Subject</th>
                        <th>Points</th>
                        <th>Used In</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($questions as $question)
                        <tr>
                            <td>{{ $question->id }}</td>
                            <td>
                                <div class="question-text">
                                    <strong>{{ Str::limit($question->question_text, 70) }}</strong>
                                    @if($question->explanation)
                                        <small class="d-block text-muted mt-1">
                                            <i class="fas fa-info-circle"></i>
                                            {{ Str::limit($question->explanation, 50) }}
                                        </small>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @php
                                    $typeColors = [
                                        'multiple_choice_single' => 'primary',
                                        'multiple_choice_multiple' => 'info',
                                        'true_false' => 'success',
                                        'fill_blank' => 'warning'
                                    ];
                                @endphp
                                <span class="badge bg-{{ $typeColors[$question->question_type] ?? 'secondary' }}">
                                {{ strtoupper(str_replace('_', ' ', $question->question_type)) }}
                            </span>
                            </td>
                            <td>
                            <span class="badge bg-light text-dark">
                                {{ $question->subject->name }}
                            </span>
                            </td>
                            <td>
                                <span class="badge bg-dark">{{ $question->points }}</span>
                            </td>
                            <td>
                            <span class="badge bg-secondary">
                                {{ $question->exams_count ?? 0 }} exams
                            </span>
                            </td>
                            <td>
                                <div class="btn-group gap-2" role="group">
                                    <a href="{{ route('questions.show', $question) }}"
                                       class="btn btn-sm btn-info" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('questions.edit', $question) }}"
                                       class="btn btn-sm btn-warning" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="{{ route('questions.destroy', $question) }}"
                                          method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete this question?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete"
                                            {{ $question->exams_count > 0 ? 'disabled' : '' }}>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="fas fa-question-circle fa-3x mb-3"></i>
                                    <p>No questions found.</p>
                                    <a href="{{ route('questions.create') }}" class="btn btn-primary">
                                        Create First Question
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($questions->hasPages())
                <div class="d-flex justify-content-center mt-4">
                    {{ $questions->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
