@extends('layouts.app')

@section('title', 'Question Details')
@section('page-title', 'Question Details')

@section('content')
    <div class="row">
        <!-- Main Question Details -->
        <div class="col-md-8">
            <!-- Question Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-question-circle me-2"></i>Question</h5>
                    <span class="badge bg-light text-dark p-2">
                    <i class="bi bi-star me-1"></i> {{ $question->points }} Points
                </span>
                </div>
                <div class="card-body">
                    <div class="question-text p-3 bg-light rounded mb-4">
                        <p class="lead mb-0">{{ $question->question_text }}</p>
                    </div>

                    <!-- Question Type Badge -->
                    <div class="mb-4">
                        @php
                            $typeColors = [
                                'mcq_single' => 'primary',
                                'mcq_multiple' => 'info',
                                'true_false' => 'success',
                                'fill_blank' => 'warning'
                            ];
                            $typeIcons = [
                                'mcq_single' => 'bi-dot-circle',
                                'mcq_multiple' => 'bi-check-double',
                                'true_false' => 'bi-toggle-on',
                                'fill_blank' => 'bi-pencil'
                            ];
                            $typeLabels = [
                                'mcq_single' => 'Multiple Choice (Single Answer)',
                                'mcq_multiple' => 'Multiple Choice (Multiple Answers)',
                                'true_false' => 'True or False',
                                'fill_blank' => 'Fill in the Blank'
                            ];
                        @endphp
                        <span class="badge bg-{{ $typeColors[$question->question_type] ?? 'secondary' }} p-3 me-2">
                        <i class="bi {{ $typeIcons[$question->question_type] ?? 'bi-question' }} me-2"></i>
                        {{ $typeLabels[$question->question_type] ?? ucfirst(str_replace('_', ' ', $question->question_type)) }}
                    </span>

                        <span class="badge bg-secondary p-3">
                        <i class="bi bi-book me-2"></i>
                        Subject: {{ $question->subject->name }}
                    </span>
                    </div>

                    <!-- Options and Answers Section -->
                    <div class="answers-section">
                        <h6 class="border-bottom pb-2 mb-3">
                            <i class="bi bi-check-circle text-success me-2"></i>
                            Correct Answer(s)
                        </h6>

                        @switch($question->question_type)
                            @case('mcq_single')
                            @case('mcq_multiple')
                                <div class="row">
                                    @php
                                        $options = $question->options;
                                        $correctAnswers = $question->correct_answers;

                                        if (!is_array($options)) {
                                            $options = $options ? json_decode($options, true) : [];
                                        }
                                        if (!is_array($correctAnswers)) {
                                            $correctAnswers = $correctAnswers ? json_decode($correctAnswers, true) : [];
                                        }

                                    @endphp

                                    @forelse($options as $letter => $option)
                                        <div class="col-md-6 mb-3">
                                            <div class="card h-100 {{ in_array($letter, $correctAnswers) ? 'border-success bg-success-light' : '' }}">
                                                <div class="card-body d-flex align-items-center">
                                                    <div class="me-3">
                                                    <span class="badge {{ in_array($letter, $correctAnswers) ? 'bg-success' : 'bg-secondary' }} p-3">
                                                        {{ $letter }}
                                                    </span>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <p class="mb-0">{{ $option }}</p>
                                                    </div>
                                                    @if(in_array($letter, $correctAnswers))
                                                        <div class="ms-2">
                                                            <i class="bi bi-check-circle text-success fa-2x"></i>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="col-12">
                                            <div class="alert alert-warning mb-0">
                                                No options found for this question.
                                            </div>
                                        </div>
                                    @endforelse
                                </div>
                                @break

                            @case('true_false')
                                @php
                                    $correctAnswers = is_array($question->correct_answers) ? $question->correct_answers : json_decode($question->correct_answers, true);
                                    $correctValue = $correctAnswers[0] ?? '';
                                @endphp
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card {{ $correctValue == 'true' ? 'border-success bg-success-light' : '' }}">
                                            <div class="card-body text-center">
                                                <h3 class="mb-0">
                                                    <span class="badge bg-success p-3">True</span>
                                                    @if($correctValue == 'true')
                                                        <i class="bi bi-check-circle text-success ms-3 fa-2x"></i>
                                                    @endif
                                                </h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card {{ $correctValue == 'false' ? 'border-success bg-success-light' : '' }}">
                                            <div class="card-body text-center">
                                                <h3 class="mb-0">
                                                    <span class="badge bg-danger p-3">False</span>
                                                    @if($correctValue == 'false')
                                                        <i class="bi bi-check-circle text-success ms-3 fa-2x"></i>
                                                    @endif
                                                </h3>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @break

                            @case('fill_blank')
                                @php
                                    $correctAnswers = is_array($question->correct_answers) ? $question->correct_answers : json_decode($question->correct_answers, true);
                                @endphp
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    The blank(s) in the question should be filled with:
                                </div>
                                <div class="row">
                                    @foreach($correctAnswers as $index => $answer)
                                        <div class="col-md-4 mb-3">
                                            <div class="card border-success">
                                                <div class="card-body text-center">
                                                    <span class="badge bg-success p-2 mb-2">Answer {{ $index + 1 }}</span>
                                                    <h5 class="mb-0">"{{ $answer }}"</h5>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @break
                        @endswitch
                    </div>

                    <!-- Explanation -->
                    @if($question->explanation)
                        <div class="explanation-section mt-4">
                            <h6 class="border-bottom pb-2 mb-3">
                                <i class="bi bi-info-circle text-info me-2"></i>
                                Explanation
                            </h6>
                            <div class="alert alert-info">
                                {{ $question->explanation }}
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Usage in Exams -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-file-alt me-2"></i>Used in Exams</h5>
                </div>
                <div class="card-body">
                    @if($question->exams->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                <tr>
                                    <th>Exam Title</th>
                                    <th>Subject</th>
                                    <th>Points</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($question->exams as $exam)
                                    <tr>
                                        <td>{{ $exam->title }}</td>
                                        <td>{{ $exam->subject->name }}</td>
                                        <td>
                                        <span class="badge bg-dark">
                                            {{ $exam->pivot->points_override ?? $question->points }}
                                        </span>
                                        </td>
                                        <td>
                                            <a href="{{route('exams.show',$exam->id)}}" class="btn btn-sm btn-outline-info">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            This question is not used in any exams yet.
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar Information -->
        <div class="col-md-4">
            <!-- Metadata Card -->
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Metadata</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless">
                        <tr>
                            <th width="40%">ID:</th>
                            <td><span class="badge bg-dark">#{{ $question->id }}</span></td>
                        </tr>
                        <tr>
                            <th>Subject:</th>
                            <td>{{ $question->subject->name }}</td>
                        </tr>
                        <tr>
                            <th>Created By:</th>
                            <td>{{ $question->creator->name ?? 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Created At:</th>
                            <td>{{ $question->created_at->format('M d, Y h:i A') }}</td>
                        </tr>
                        <tr>
                            <th>Last Updated:</th>
                            <td>{{ $question->updated_at->diffForHumans() }}</td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
                                @if($question->trashed())
                                    <span class="badge bg-danger">Deleted</span>
                                @else
                                    <span class="badge bg-success">Active</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Statistics Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="border rounded p-3">
                                <h3 class="mb-1">{{ $question->exams->count() }}</h3>
                                <small class="text-muted">Exams</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="border rounded p-3">
                                <h3 class="mb-1">{{ $question->points }}</h3>
                                <small class="text-muted">Points</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Card -->
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('questions.edit', $question) }}" class="btn btn-warning">
                            <i class="bi bi-pencil   me-2"></i> Edit Question
                        </a>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#copyModal">
                            <i class="bi bi-copy me-2"></i> Duplicate Question
                        </button>
                        <a href="{{ route('questions.create') }}?subject_id={{ $question->subject_id }}" class="btn btn-info">
                            <i class="bi bi-plus me-2"></i> Add Similar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this question?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>{{ Str::limit($question->question_text, 100) }}</strong>
                    </div>

                    @if($question->exams->count() > 0)
                        <div class="alert alert-danger">
                            <i class="bi bi-ban me-2"></i>
                            This question is used in <strong>{{ $question->exams->count() }} exam(s)</strong>.
                            Deleting it will remove it from those exams.
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="{{ route('questions.destroy', $question) }}" method="POST" class="d-inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Delete Question
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Copy Modal -->
    <div class="modal fade" id="copyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Duplicate Question</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="{{ route('questions.duplicate', $question) }}" method="POST">
                    @csrf
                    <div class="modal-body">
                        <p>Create a copy of this question?</p>
                        <div class="mb-3">
                            <label for="new_subject_id" class="form-label">Subject (Optional)</label>
                            <select name="subject_id" id="new_subject_id" class="form-select">
                                <option value="">Same Subject ({{ $question->subject->name }})</option>
                                @foreach(App\Models\Subject::all() as $subject)
                                    <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="include_explanation" id="include_explanation" checked>
                            <label class="form-check-label" for="include_explanation">
                                Include explanation
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-copy"></i> Create Duplicate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection


