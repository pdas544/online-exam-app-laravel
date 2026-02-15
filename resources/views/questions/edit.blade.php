@extends('layouts.app')

@section('title', 'Edit Question')
@section('page-title', 'Edit Question')



@section('content')
    <div class="d-flex justify-content-between align-items-center m-3">
        <div>
            <h1 class="h3">Question Management</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin Dashboard</a></li>
                    <li class="breadcrumb-item active">Questions</li>
                </ol>
            </nav>
        </div>
        @section('header-buttons-right')

            <div class="d-flex justify-content-end m-3 gap-1">
                <a href="{{ route('questions.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Add Question
                </a>

                <a href="{{ route('questions.index') }}" class="btn btn-primary">
                    <i class="bi bi-eye"></i> View All Questions
                </a>
            </div>
        @endsection

    </div>
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0"><i class="bi bi-pencil me-2"></i>Edit Question #{{ $question->id }}</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('questions.update', $question) }}" id="questionForm">
                        @csrf
                        @method('PUT')

                        <!-- Basic Information -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="subject_id" class="form-label">Subject *</label>
                                    <select class="form-select @error('subject_id') is-invalid @enderror"
                                            id="subject_id" name="subject_id" required>
                                        <option value="">Select Subject</option>
                                        @foreach($subjects as $subject)
                                            <option value="{{ $subject->id }}"
                                                {{ old('subject_id', $question->subject_id) == $subject->id ? 'selected' : '' }}>
                                                {{ $subject->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('subject_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="question_type" class="form-label">Question Type *</label>
                                    <select class="form-select @error('question_type') is-invalid @enderror"
                                            id="question_type" name="question_type" required>
                                        <option value="">Select Type</option>
                                        <option value="mcq_single" {{ old('question_type', $question->question_type) == 'mcq_single' ? 'selected' : '' }}>
                                            Multiple Choice (Single Answer)
                                        </option>
                                        <option value="mcq_multiple" {{ old('question_type', $question->question_type) == 'mcq_multiple' ? 'selected' : '' }}>
                                            Multiple Choice (Multiple Answers)
                                        </option>
                                        <option value="true_false" {{ old('question_type', $question->question_type) == 'true_false' ? 'selected' : '' }}>
                                            True/False
                                        </option>
                                        <option value="fill_blank" {{ old('question_type', $question->question_type) == 'fill_blank' ? 'selected' : '' }}>
                                            Fill in the Blank
                                        </option>
                                    </select>
                                    @error('question_type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Question Text -->
                        <div class="mb-3">
                            <label for="question_text" class="form-label">Question Text *</label>
                            <textarea class="form-control @error('question_text') is-invalid @enderror"
                                      id="question_text" name="question_text" rows="3"
                                      placeholder="Enter your question here..." required>{{ old('question_text', $question->question_text) }}</textarea>
                            @error('question_text')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Dynamic Fields Based on Question Type -->
                        <div id="type-specific-fields"
                             data-question-type="{{ $question->question_type }}"
                             data-old-options='@json(old("options", $question->options ?? []))'
                             data-old-correct='@json(old("correct_answers", $question->correct_answers ?? []))'>
                        </div>

                        @error('correct_answers')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                        @enderror

                        <!-- Warning if question is used in exams -->
                        @if($question->exams->count() > 0)
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Note:</strong> This question is used in {{ $question->exams->count() }} exam(s).
                                Changes will affect all exams using this question.
                            </div>
                        @endif

                        <!-- Points and Explanation -->
                        <div class="row mt-4">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="points" class="form-label">Points *</label>
                                    <input type="number" class="form-control @error('points') is-invalid @enderror"
                                           id="points" name="points" min="1" max="10"
                                           value="{{ old('points', $question->points) }}" required>
                                    @error('points')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-9">
                                <div class="mb-3">
                                    <label for="explanation" class="form-label">Explanation (Optional)</label>
                                    <textarea class="form-control @error('explanation') is-invalid @enderror"
                                              id="explanation" name="explanation" rows="2"
                                              placeholder="Explain the answer...">{{ old('explanation', $question->explanation) }}</textarea>
                                    @error('explanation')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="{{ route('questions.show', $question) }}" class="btn btn-outline-secondary me-2">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-save"></i> Update Question
                            </button>
                        </div>
                    </form>

                    <!-- Templates (kept out of JS) -->
                    <template id="tpl-mcq">
                        <div class="card mb-3" data-type-ui="mcq">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Multiple Choice Options</h6>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-success" data-action="add-option">Add Option</button>
                                    <button type="button" class="btn btn-danger" data-action="remove-option">Remove Last</button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="options-container"></div>
                                <div class="mt-3">
                                    <label class="form-label" id="mcq-correct-label"></label>
                                    <div id="correct-answers-container" class="row"></div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template id="tpl-true-false">
                        <div class="card mb-3" data-type-ui="true_false">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">True/False Options</h6>
                            </div>
                            <div class="card-body">
                                <label class="form-label">Correct Answer *</label>
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio"
                                                   name="correct_answers[]" value="true" id="correctTrue">
                                            <label class="form-check-label" for="correctTrue">True</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio"
                                                   name="correct_answers[]" value="false" id="correctFalse">
                                            <label class="form-check-label" for="correctFalse">False</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template id="tpl-fill-blank">
                        <div class="card mb-3" data-type-ui="fill_blank">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Fill in the Blank</h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    Use underscores _____ in the question text to indicate blank spaces.
                                </div>

                                <label class="form-label">Correct Answer(s) *</label>
                                <div id="fill-blank-answers"></div>

                                <button type="button" class="btn btn-sm btn-success mt-2" data-action="add-blank-answer">
                                    Add Alternative Answer
                                </button>
                            </div>
                        </div>
                    </template>

                </div>
            </div>
        </div>
    </div>
@endsection
