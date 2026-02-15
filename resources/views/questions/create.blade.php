@extends('layouts.app')

@section('title', 'Create New Question')
@section('page-title', 'Create New Question')

@section('header-buttons')
    <a href="{{ route('questions.index') }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left"></i> Back to Questions
    </a>
@endsection

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>New Question</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('questions.store') }}" id="questionForm">
                        @csrf

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
                                                {{ old('subject_id', request('subject_id')) == $subject->id ? 'selected' : '' }}>
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
                                        <option value="mcq_single" {{ old('question_type') == 'mcq_single' ? 'selected' : '' }}>
                                            Multiple Choice (Single Answer)
                                        </option>
                                        <option value="mcq_multiple" {{ old('question_type') == 'mcq_multiple' ? 'selected' : '' }}>
                                            Multiple Choice (Multiple Answers)
                                        </option>
                                        <option value="true_false" {{ old('question_type') == 'true_false' ? 'selected' : '' }}>
                                            True/False
                                        </option>
                                        <option value="fill_blank" {{ old('question_type') == 'fill_blank' ? 'selected' : '' }}>
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
                                      placeholder="Enter your question here..." required>{{ old('question_text') }}</textarea>
                            @error('question_text')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Dynamic Fields Based on Question Type -->
                        <div id="type-specific-fields"
                             data-old-options='@json(old("options", []))'
                             data-old-correct='@json(old("correct_answers", []))'>
                        </div>

                        @error('correct_answers')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                        @enderror

                        <!-- Points and Explanation -->
                        <div class="row mt-4">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="points" class="form-label">Points *</label>
                                    <input type="number" class="form-control @error('points') is-invalid @enderror"
                                           id="points" name="points" min="1" max="10"
                                           value="{{ old('points', 1) }}" required>
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
                                              placeholder="Explain the answer...">{{ old('explanation') }}</textarea>
                                    @error('explanation')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <button type="reset" class="btn btn-outline-secondary me-2">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Create Question
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
