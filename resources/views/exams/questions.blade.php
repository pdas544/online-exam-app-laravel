@extends('layouts.app')

@section('title', 'Manage Exam Questions')
@section('page-title', 'Manage Questions: ' . $exam->title)

{{--@section('header-buttons')--}}
{{--    <a href="{{ route('exams.show', $exam) }}" class="btn btn-outline-info me-2">--}}
{{--        <i class="bi bi-eye"></i> View Exam--}}
{{--    </a>--}}
{{--    <a href="{{ route('exams.edit', $exam) }}" class="btn btn-outline-warning">--}}
{{--        <i class="bi bi-edit"></i> Edit Details--}}
{{--    </a>--}}
{{--@endsection--}}

@section('content')
    @if(session('success'))
        <div class="alert alert-success m-3">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('info'))
        <div class="alert alert-info m-3">
            {{ session('info') }}
        </div>
    @endif

    @php
        $examQuestionsConfig = [
            'examId' => $exam->id,
            'csrf' => csrf_token(),
            'endpoints' => [
                'reorder' => route('exams.questions.reorder', $exam),
                'points' => url('/exams/' . $exam->id . '/questions/' . '{question}' . '/points'),
                'attachOne' => route('exams.questions.add', $exam),
                'attachBulk' => route('exams.questions.bulk', $exam),
                'detach' => url('/exams/' . $exam->id . '/questions/' . '{question}'),
            ],
        ];
    @endphp
    <div class="row"
         id="exam-questions-page"
         data-config='@json($examQuestionsConfig)'>
        <!-- Current Questions -->
        <div class="col-md-7">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-list me-2"></i>Current Questions</h5>
                    <span class="badge bg-light text-dark p-2" id="total-marks-badge">Total Marks: {{ $exam->total_marks }}</span>
                </div>
                <div class="card-body">
                    @if($exam->questions->count() > 0)
                        <div id="questions-container" data-exam-id="{{ $exam->id }}">
                            @foreach($exam->questions as $index => $question)
                                <div class="card mb-2 question-item" data-question-id="{{ $question->id }}" data-order="{{ $index + 1 }}">
                                    <div class="card-body py-2">
                                        <div class="row align-items-center">
                                            <div class="col-md-1 text-center">
                                                <span class="badge bg-secondary order-badge">{{ $index + 1 }}</span>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="question-text">
                                                    <strong>{{ Str::limit($question->question_text, 60) }}</strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        Type:
                                                        @php
                                                            $typeLabels = [
                                                                'mcq_single' => 'MCQ (Single)',
                                                                'mcq_multiple' => 'MCQ (Multi)',
                                                                'true_false' => 'True/False',
                                                                'fill_blank' => 'Fill Blank'
                                                            ];
                                                        @endphp
                                                        {{ $typeLabels[$question->question_type] ?? $question->question_type }}
                                                    </small>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="input-group input-group-sm">
                                                    <input type="number"
                                                           class="form-control points-input"
                                                           value="{{ $question->pivot->points_override ?? $question->points }}"
                                                           min="1" max="10"
                                                           data-question-id="{{ $question->id }}"
                                                           data-original="{{ $question->points }}">
                                                    <span class="input-group-text">pts</span>
                                                </div>
                                                @if($question->pivot->points_override)
                                                    <small class="text-warning d-block">Orig: {{ $question->points }}</small>
                                                @endif
                                            </div>
                                            <div class="col-md-3">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button type="button" class="btn btn-info view-question"
                                                            data-question-id="{{ $question->id }}"
                                                            data-question-text="{{ $question->question_text }}"
                                                            data-question-type="{{ $question->question_type }}"
                                                            data-options='@json($question->options)'
                                                            data-correct-answers='@json($question->correct_answers)'>
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-danger remove-question"
                                                            data-question-id="{{ $question->id }}"
                                                            data-question-text="{{ Str::limit($question->question_text, 30) }}">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                    <span class="btn btn-light drag-handle" style="cursor: move;">
                                                    <i class="bi bi-grip-vertical"></i>
                                                </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Reorder Instructions -->
{{--                        <div class="alert alert-info mt-3">--}}
{{--                            <i class="bi bi-info-circle me-2"></i>--}}
{{--                            Drag the <i class="bi bi-grip-vertical"></i> handle to reorder questions. Changes are saved automatically.--}}
{{--                        </div>--}}
                    @else
                        <div class="text-center py-4">
                            <i class="bi bi-question-circle fs-3 text-muted mb-3"></i>
                            <p>No questions added to this exam yet.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Add Questions Panel -->
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Add Questions</h5>
                </div>
                <div class="card-body">
                    <!-- Search and Filter -->
                    <div class="mb-3">
                        <input type="text" id="questionSearch" class="form-control"
                               placeholder="Search questions...">
                    </div>

                    <!-- Available Questions -->
                    <div id="available-questions" style="max-height: 400px; overflow-y: auto;">
                        @foreach($availableQuestions as $question)
                            <div class="card mb-2 available-question" data-question-id="{{ $question->id }}">
                                <div class="card-body py-2">
                                    <div class="row align-items-center">
                                        <div class="col-md-9">
                                            <div class="question-text">
                                                <strong>{{ Str::limit($question->question_text, 50) }}</strong>
                                                <br>
                                                <small class="text-muted">
                                                    <span class="badge bg-secondary">{{ $question->points }} pts</span>
                                                    @php
                                                        $typeLabels = [
                                                            'mcq_single' => 'MCQ (Single)',
                                                            'mcq_multiple' => 'MCQ (Multi)',
                                                            'true_false' => 'True/False',
                                                            'fill_blank' => 'Fill Blank'
                                                        ];
                                                    @endphp
                                                    {{ $typeLabels[$question->question_type] ?? $question->question_type }}
                                                </small>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <button type="button" class="btn btn-sm btn-success add-question w-100"
                                                    data-question-id="{{ $question->id }}">
                                                <i class="bi bi-plus-circle"></i> Add
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Pagination -->
                    <div class="mt-3">
                        {{ $availableQuestions->appends(request()->query())->links() }}
                    </div>

                    <!-- Quick Add Multiple -->
                    <div class="mt-4">
                        <h6>Quick Add Multiple</h6>
                        <div class="input-group mb-2">
                            <input type="text" id="bulkSearch" class="form-control"
                                   placeholder="Type to search questions...">
                            <button class="btn btn-outline-secondary" type="button" id="clearBulkSearch">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                        <div id="bulkSelection" style="max-height: 150px; overflow-y: auto;" class="border rounded p-2 mb-2">
                            <div class="text-center text-muted py-2" id="bulkSearchPlaceholder">
                                <small>Start typing to search questions...</small>
                            </div>
                            <!-- Bulk selection checkboxes will appear here -->
                        </div>
                        <button type="button" class="btn btn-primary flex-grow-1" id="bulkAddBtn" disabled>
                            <i class="bi bi-plus-circle"></i> Add Selected (<span id="selectedCount">0</span>)
                        </button>

                    </div>
                </div>
            </div>
        </div>
    </div>
    @php
        $allQuestionsJson = $allQuestions->map(function ($q) {
            return [
                'id' => $q->id,
                'text' => $q->question_text,
                'points' => $q->points,
            ];
        })->values();
    @endphp
    <script type="application/json" id="all-questions-json">
        @json($allQuestionsJson)
    </script>

    <!-- Question Preview Modal -->
    <div class="modal fade" id="questionPreviewModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">Question Preview</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="previewQuestionText"></p>

                    <div id="previewOptions" class="mt-3">
                        <!-- Options will be populated here -->
                    </div>

                    <div class="mt-3">
                        <strong>Correct Answer(s):</strong>
                        <p id="previewCorrectAnswers"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Remove Question Modal -->
    <div class="modal fade" id="removeQuestionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Remove</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to remove this question from the exam?</p>
                    <p class="fw-bold" id="removeQuestionText"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="removeQuestionForm" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Remove</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection


