@extends('layouts.app')

@section('title', 'Edit Exam')
@section('page-title', 'Edit Exam: ' . $exam->title)


@section('content')
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0"><i class="bi bi-pencil me-2"></i>Edit Exam #{{ $exam->id }}</h5>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i> {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('exams.update', $exam) }}">
                        @csrf
                        @method('PUT')

                        <!-- Basic Information -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Exam Title *</label>
                                    <input type="text" class="form-control @error('title') is-invalid @enderror"
                                           id="title" name="title" value="{{ old('title', $exam->title) }}"
                                           required>
                                    @error('title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="subject_id" class="form-label">Subject *</label>
                                    <select class="form-select @error('subject_id') is-invalid @enderror"
                                            id="subject_id" name="subject_id" required>
                                        <option value="">Select Subject</option>
                                        @foreach($subjects as $subject)
                                            <option value="{{ $subject->id }}"
                                                {{ old('subject_id', $exam->subject_id) == $subject->id ? 'selected' : '' }}>
                                                {{ $subject->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('subject_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="academic_year" class="form-label">Academic Year</label>
                                    <input type="text" name="academic_year" id="academic_year" class="form-control @error('academic_year') is-invalid @enderror"
                                           value="{{ old('academic_year', $exam->academic_year) }}">
                                    @error('academic_year')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="mb-3">
                                    <label for="semester" class="form-label">Semester</label>
                                    <input type="text" id="semester" name="semester" class="form-control @error('semester') is-invalid @enderror"
                                           value="{{ old('semester', $exam->semester) }}">
                                    @error('semester')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror"
                                      id="description" name="description" rows="3"
                                      placeholder="Describe the exam purpose, instructions, etc.">{{ old('description', $exam->description) }}</textarea>
                            @error('description')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Exam Settings -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="time_limit" class="form-label">Time Limit (minutes) *</label>
                                    <input type="number" class="form-control @error('time_limit') is-invalid @enderror"
                                           id="time_limit" name="time_limit" value="{{ old('time_limit', $exam->time_limit) }}"
                                           min="5" max="480" required>
                                    @error('time_limit')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="passing_marks" class="form-label">Passing Marks *</label>
                                    <input type="number" class="form-control @error('passing_marks') is-invalid @enderror"
                                           id="passing_marks" name="passing_marks" value="{{ old('passing_marks', $exam->passing_marks) }}"
                                           min="0" required>
                                    @error('passing_marks')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="max_attempts" class="form-label">Max Attempts *</label>
                                    <input type="number" class="form-control @error('max_attempts') is-invalid @enderror"
                                           id="max_attempts" name="max_attempts" value="{{ old('max_attempts', $exam->max_attempts) }}"
                                           min="1" max="10" required>
                                    @error('max_attempts')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Total Marks Display -->
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-calculator me-2"></i>
                            <strong>Total Marks:</strong> {{ $exam->total_marks }}
                            (calculated from {{ $exam->questions->count() }} questions)
                        </div>

                        <!-- Shuffle Options -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title">Question Shuffling</h6>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox"
                                                   id="shuffle_questions" name="shuffle_questions" value="1"
                                                {{ old('shuffle_questions', $exam->shuffle_questions) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="shuffle_questions">
                                                Shuffle question order for each student
                                            </label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox"
                                                   id="shuffle_options" name="shuffle_options" value="1"
                                                {{ old('shuffle_options', $exam->shuffle_options) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="shuffle_options">
                                                Shuffle option order within questions
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Availability Window -->
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title">Availability Window (Optional)</h6>
                                        <div class="mb-2">
                                            <label for="available_from" class="form-label">Available From</label>
                                            <input type="datetime-local" class="form-control @error('available_from') is-invalid @enderror"
                                                   id="available_from" name="available_from"
                                                   value="{{ old('available_from', $exam->available_from ? $exam->available_from->format('Y-m-d\TH:i') : '') }}">
                                        </div>
                                        <div>
                                            <label for="available_to" class="form-label">Available To</label>
                                            <input type="datetime-local" class="form-control @error('available_to') is-invalid @enderror"
                                                   id="available_to" name="available_to"
                                                   value="{{ old('available_to', $exam->available_to ? $exam->available_to->format('Y-m-d\TH:i') : '') }}">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Exam Status *</label>
                                <div class="border p-3 rounded">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="status"
                                               id="status_draft" value="draft"
                                            {{ old('status', $exam->status) == 'draft' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="status_draft">
                                            <span class="badge bg-secondary">Draft</span> - Save as draft, not visible to students
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="status"
                                               id="status_published" value="published"
                                            {{ old('status', $exam->status) == 'published' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="status_published">
                                            <span class="badge bg-success">Published</span> - Visible to students (if within availability window)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="status"
                                               id="status_archived" value="archived"
                                            {{ old('status', $exam->status) == 'archived' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="status_archived">
                                            <span class="badge bg-danger">Archived</span> - Hidden from students
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="alert alert-warning h-100 d-flex align-items-center">
                                    <i class="bi bi-exclamation-triangle fa-2x me-3"></i>
                                    <div>
                                        <strong>Important:</strong> Changes to published exams will affect students immediately.
                                        Consider creating a new version for major changes.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="{{ route('exams.show', $exam) }}" class="btn btn-outline-secondary me-2">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-save"></i> Update Exam
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set min date for available_to based on available_from
            const fromInput = document.getElementById('available_from');
            const toInput = document.getElementById('available_to');

            if (fromInput && toInput) {
                fromInput.addEventListener('change', function() {
                    toInput.min = this.value;
                });
            }
        });
    </script>
@endpush
