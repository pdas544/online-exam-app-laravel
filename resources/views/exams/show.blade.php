@extends('layouts.app')

@section('title', 'Exam Details')
@section('page-title', 'Exam: ' . $exam->title)


@section('content')
    @if(session('success'))
        <div class="alert alert-success m-3">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger m-3">
            {{ session('error') }}
        </div>
    @endif
    <div class="row">
        <!-- Main Content - Exam Details -->
        <div class="col-md-8">
            <!-- Exam Info Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Exam Information</h5>
                    <span class="badge bg-light text-dark p-2">
                    Status:
                    @php
                        $statusColors = [
                            'draft' => 'secondary',
                            'published' => 'success',
                            'archived' => 'danger'
                        ];
                    @endphp
                    <span class="badge bg-{{ $statusColors[$exam->status] }} ms-1">{{ ucfirst($exam->status) }}</span>
                </span>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h4>{{ $exam->title }}</h4>
                            <p class="text-muted">{{ $exam->description ?? 'No description provided.' }}</p>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 bg-light">
                                <small class="text-muted d-block">Created by:</small>
                                <strong>{{ $exam->teacher->name }}</strong>
                                <small class="text-muted d-block mt-2">Created on:</small>
                                <strong>{{ $exam->created_at->format('M d, Y h:i A') }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Exam Settings Card -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-gear me-2"></i>Exam Settings</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3 text-center">
                                <i class="bi bi-clock fs-3 text-primary mb-2"></i>
                                <h6>Time Limit</h6>
                                <h5>{{ $exam->time_limit }} minutes</h5>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3 text-center">
                                <i class="bi bi-arrow-clockwise fs-3 text-success mb-2"></i>
                                <h6>Max Attempts</h6>
                                <h5>{{ $exam->max_attempts }}</h5>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3 text-center">
                                <i class="bi bi-star fs-3 text-warning mb-2"></i>
                                <h6>Passing Marks</h6>
                                <h5>{{ $exam->passing_marks }}</h5>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="border rounded p-3">
                                <h6><i class="bi bi-calendar me-2 text-info"></i>Availability Window</h6>
                                @if($exam->available_from && $exam->available_to)
                                    <p class="mb-0">
                                        From: <strong>{{ $exam->available_from->format('M d, Y h:i A') }}</strong><br>
                                        To: <strong>{{ $exam->available_to->format('M d, Y h:i A') }}</strong>
                                    </p>
                                    @if($exam->isAvailable())
                                        <span class="badge bg-success mt-2">Currently Available</span>
                                    @else
                                        <span class="badge bg-danger mt-2">Not Available</span>
                                    @endif
                                @else
                                    <p class="text-muted mb-0">No time restrictions</p>
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3">
                                <h6><i class="bi bi-shuffle me-2 text-danger"></i>Shuffle Settings</h6>
                                <p class="mb-0">
                                    Shuffle Questions:
                                    @if($exam->shuffle_questions)
                                        <span class="badge bg-success">Yes</span>
                                    @else
                                        <span class="badge bg-secondary">No</span>
                                    @endif
                                    <br>
                                    Shuffle Options:
                                    @if($exam->shuffle_options)
                                        <span class="badge bg-success">Yes</span>
                                    @else
                                        <span class="badge bg-secondary">No</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Questions List -->
            <div class="card">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-question me-2"></i>Questions ({{ $exam->questions->count() }})</h5>
                    <span class="badge bg-light text-dark p-2">Total Marks: {{ $exam->total_marks }}</span>
                </div>
                <div class="card-body">
                    @if($exam->questions->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="50%">Question</th>
                                    <th width="15%">Type</th>
                                    <th width="10%">Points</th>
                                    <th width="10%">Original Points</th>
                                    <th width="10%">Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($exam->questions as $index => $question)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            <div class="question-text">
                                                <strong>{{ Str::limit($question->question_text, 60) }}</strong>
                                                @if($question->pivot->points_override)
                                                    <small class="d-block text-warning">
                                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                                        Points overridden
                                                    </small>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            @php
                                                $typeColors = [
                                                    'mcq_single' => 'primary',
                                                    'mcq_multiple' => 'info',
                                                    'true_false' => 'success',
                                                    'fill_blank' => 'warning'
                                                ];
                                                $typeLabels = [
                                                    'mcq_single' => 'MCQ (Single)',
                                                    'mcq_multiple' => 'MCQ (Multi)',
                                                    'true_false' => 'True/False',
                                                    'fill_blank' => 'Fill Blank'
                                                ];
                                            @endphp
                                            <span class="badge bg-{{ $typeColors[$question->question_type] ?? 'secondary' }}">
                                            {{ $typeLabels[$question->question_type] ?? $question->question_type }}
                                        </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-dark">{{ $question->pivot->points_override ?? $question->points }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">{{ $question->points }}</span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ route('questions.show', $question) }}"
                                                   class="btn btn-info" title="View Question">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <button type="button"
                                                        class="btn btn-warning btn-edit-points ms-1"
                                                        data-question-id="{{ $question->id }}"
                                                        data-question-text="{{ Str::limit($question->question_text, 30) }}"
                                                        data-current-points="{{ $question->pivot->points_override ?? $question->points }}"
                                                        title="Edit Points">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                                <tfoot class="table-light">
                                <tr>
                                    <th colspan="3" class="text-end">Total:</th>
                                    <th>{{ $exam->total_marks }}</th>
                                    <th colspan="2"></th>
                                </tr>
                                </tfoot>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-question-circle fa-4x text-muted mb-3"></i>
                            <h5>No Questions Added</h5>
                            <p class="text-muted">This exam doesn't have any questions yet.</p>
                            <a href="{{ route('exams.questions', $exam) }}" class="btn btn-primary">
                                <i class="bi bi-plus"></i> Add Questions
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Statistics Card -->
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="border rounded p-3">
                                <h3>{{ $stats['total_questions'] }}</h3>
                                <small class="text-muted">Total Questions</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="border rounded p-3">
                                <h3>{{ $stats['total_marks'] }}</h3>
                                <small class="text-muted">Total Marks</small>
                            </div>
                        </div>
                    </div>

                    <h6 class="mt-3 mb-2">Questions by Type:</h6>
                    <ul class="list-group">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            MCQ (Single)
                            <span class="badge bg-primary rounded-pill">{{ $stats['mcq_single_count'] }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            MCQ (Multiple)
                            <span class="badge bg-info rounded-pill">{{ $stats['mcq_multiple_count'] }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            True/False
                            <span class="badge bg-success rounded-pill">{{ $stats['true_false_count'] }}</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Fill in Blank
                            <span class="badge bg-warning rounded-pill">{{ $stats['fill_blank_count'] }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Quick Actions Card -->
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0"><i class="bi bi-lightning me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('exams.questions', $exam) }}" class="btn btn-primary">
                            <i class="bi bi-list me-2"></i> Manage Questions
                        </a>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#previewModal">
                            <i class="bi bi-eye me-2"></i> Preview Exam
                        </button>
                        <a href="{{ route('exams.edit', $exam) }}" class="btn btn-warning">
                            <i class="bi bi-pencil me-2"></i> Edit Exam Details
                        </a>
                        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="bi bi-trash me-2"></i> Delete Exam
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Exam Preview: {{ $exam->title }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        This is how students will see the exam. Questions appear in the order defined.
                    </div>

                    @foreach($exam->questions as $index => $question)
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <strong>Question {{ $index + 1 }}</strong>
                                <span class="float-end">({{ $question->pivot->points_override ?? $question->points }} points)</span>
                            </div>
                            <div class="card-body">
                                <p class="mb-3">{{ $question->question_text }}</p>

                                @if(in_array($question->question_type, ['mcq_single', 'mcq_multiple']))
                                    {{-- Check if options exist and is an array --}}
                                    @if(is_array($question->options) || is_object($question->options))
                                        <div class="ms-3">
                                            @foreach($question->options as $letter => $option)
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="radio" disabled>
                                                    <label class="form-check-label">
                                                        <strong>{{ $letter }}.</strong> {{ $option }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                @elseif($question->question_type === 'true_false')
                                    <div class="ms-3">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" disabled>
                                            <label class="form-check-label">True</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" disabled>
                                            <label class="form-check-label">False</label>
                                        </div>
                                    </div>
                                @elseif($question->question_type === 'fill_blank')
                                    <div class="ms-3">
                                        <input type="text" class="form-control" placeholder="Your answer" disabled>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Points Modal -->
    <div class="modal fade" id="editPointsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">Edit Question Points</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editPointsForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <p>Question: <strong id="questionTextDisplay"></strong></p>
                        <div class="mb-3">
                            <label for="points" class="form-label">Points *</label>
                            <input type="number" class="form-control" id="pointsInput" name="points" min="1" max="10" required>
                            <div class="form-text">Original question points: <span id="originalPointsDisplay"></span></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update Points</button>
                    </div>
                </form>
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
                    <p>Are you sure you want to delete this exam?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>{{ $exam->title }}</strong>
                    </div>
                    <p class="text-danger small">
                        This action cannot be undone. All question associations will be removed.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="{{ route('exams.destroy', $exam) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Delete Exam
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Edit Points functionality
            const editButtons = document.querySelectorAll('.btn-edit-points');
            const editPointsModal = new bootstrap.Modal(document.getElementById('editPointsModal'));
            const pointsForm = document.getElementById('editPointsForm');
            const questionTextDisplay = document.getElementById('questionTextDisplay');
            const pointsInput = document.getElementById('pointsInput');
            const originalPointsDisplay = document.getElementById('originalPointsDisplay');

            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const questionId = this.dataset.questionId;
                    const questionText = this.dataset.questionText;
                    const currentPoints = this.dataset.currentPoints;


                    questionTextDisplay.textContent = questionText;
                    pointsInput.value = currentPoints;
                    originalPointsDisplay.textContent = currentPoints;

                    editPointsModal.show();
                });
            });
        });
    </script>
@endpush

@push('styles')
    <style>
        .question-text {
            max-width: 300px;
        }
        .border.rounded.p-3 {
            transition: transform 0.2s;
        }
        .border.rounded.p-3:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
    </style>
@endpush
