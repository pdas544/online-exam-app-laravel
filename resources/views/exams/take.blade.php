@extends('layouts.app')

@section('title', 'Take Exam: ' . $session->exam->title)
@section('page-title', $session->exam->title)

@section('content')
    <div id="exam-container"
         data-session-id="{{ $session->id }}"
         data-exam-id="{{ $session->exam_id }}"
         data-config="{{ json_encode([
             'studentId' => Auth::id(),
             'csrf' => csrf_token(),
             'autoSaveInterval' => 60,
              'fullscreenRequired' => true,
              'startLocked' => $session->status === 'scheduled',
              'debug' => false,
          ]) }}">

        <!-- Header with Timer -->
        <div class="fixed-top bg-white border-bottom py-2" style="top: 0; z-index: 1030;">
            <div class="container-fluid">
                <div class="row align-items-center g-2">
                    <div class="col-12 col-md-4 order-1">
                        <h5 class="mb-0 text-truncate">{{ $session->exam->title }}</h5>
                    </div>
                    <div class="col-8 col-md-4 text-center order-2">
                        <div class="alert alert-info mb-0 py-2" id="timer-container" role="timer" aria-live="polite" aria-atomic="true">
                            <i class="bi bi-clock me-2" aria-hidden="true"></i>
                            Time Remaining: <strong id="timer">{{ $session->exam->time_limit }}:00</strong>
                        </div>
                    </div>
                    <div class="col-4 col-md-4 text-end order-3">
                        <button class="btn btn-success" id="submit-exam">
                            <i class="bi bi-check-circle me-2" aria-hidden="true"></i>Submit Exam
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="container-fluid" style="margin-top: 80px;">
            <div class="row">
                <!-- Questions Navigation Sidebar (Vertical Palette) -->
                <div class="col-md-3">
                    <div class="card sticky-top" style="top: 100px;">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">Questions Progress</h6>
                        </div>
                        <div class="card-body">
                            <div class="progress mb-3" style="height: 25px;" role="progressbar" aria-label="Answered questions progress" aria-valuemin="0" aria-valuemax="{{ $session->total_questions }}" aria-valuenow="0" id="progress-container">
                                <div id="progress-bar" class="progress-bar progress-bar-striped"
                                     style="width: 0%"></div>
                            </div>
                            <div class="mb-2" aria-live="polite">
                                <span class="badge bg-success" id="answered-count">0</span> /
                                <span>{{ $session->total_questions }}</span> Answered
                            </div>
                            <div class="mb-3" aria-live="polite">
                                <span class="badge bg-warning" id="review-count">0</span> Marked for Review
                            </div>

                            <!-- Vertical Question Palette -->
                            <div class="question-palette-vertical" style="max-height: 400px; overflow-y: auto;">
                                <div class="list-group">
                                    @foreach($session->answers as $index => $answer)
                                        <button class="list-group-item list-group-item-action nav-question d-flex justify-content-between align-items-center
                                            @if($answer->is_marked_for_review) palette-marked
                                            @elseif($answer->is_answered) list-group-item-success
                                            @else palette-unanswered
                                            @endif"
                                                data-target="{{ $answer->question_id }}"
                                                data-index="{{ $index }}">
                                            <span>Question {{ $index + 1 }}</span>
                                            <span>
                                                @if($answer->is_marked_for_review)
                                                    <i class="bi bi-bookmark-fill palette-marked-icon" title="Marked for Review"></i>
                                                @elseif($answer->is_answered)
                                                    <i class="bi bi-check-circle-fill text-success" title="Answered"></i>
                                                @else
                                                    <i class="bi bi-circle palette-unanswered-icon" title="Not Answered"></i>
                                                @endif
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Current Question Display (Single Question) -->
                <div class="col-md-9">
                    <div id="questions-container">
                        @foreach($session->answers as $index => $answer)
                            <div class="card mb-4 question-card {{ $index === 0 ? '' : 'd-none' }}"
                                 id="question-{{ $answer->question_id }}"
                                 data-question-id="{{ $answer->question_id }}"
                                 data-answered="{{ $answer->is_answered ? 'true' : 'false' }}"
                                 data-index="{{ $index }}">
                                <div class="card-header bg-light d-flex justify-content-between">
                                    <h5 class="mb-0">Question {{ $index + 1 }} of {{ $session->total_questions }}</h5>
                                    <span class="badge bg-info">Points: {{ $answer->max_points }}</span>
                                </div>
                                <div class="card-body">
                                    <p class="lead">{{ $answer->question->question_text }}</p>

                                    @php
                                        $rawAnswer = $answer->answer;
                                        if (is_string($rawAnswer)) {
                                            $decoded = json_decode($rawAnswer, true);
                                            if (json_last_error() === JSON_ERROR_NONE) {
                                                $rawAnswer = $decoded;
                                            }
                                        }

                                        $normalizedAnswer = is_array($rawAnswer)
                                            ? array_values($rawAnswer)
                                            : ($rawAnswer === null || $rawAnswer === '' ? [] : [(string) $rawAnswer]);
                                        $singleAnswer = $normalizedAnswer[0] ?? null;
                                    @endphp

                                    @switch($answer->question->question_type)
                                        @case('mcq_single')
                                            @foreach($answer->question->options ?? [] as $letter => $option)
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="radio"
                                                           name="q{{ $answer->question_id }}"
                                                           id="q{{ $answer->question_id }}{{ $letter }}"
                                                           value="{{ $letter }}"
                                                        {{ $singleAnswer === $letter ? 'checked' : '' }}>
                                                    <label class="form-check-label"
                                                           for="q{{ $answer->question_id }}{{ $letter }}">
                                                        <strong>{{ $letter }}.</strong> {{ $option }}
                                                    </label>
                                                </div>
                                            @endforeach
                                            @break

                                        @case('mcq_multiple')
                                            @foreach($answer->question->options ?? [] as $letter => $option)
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox"
                                                           name="q{{ $answer->question_id }}[]"
                                                           id="q{{ $answer->question_id }}{{ $letter }}"
                                                           value="{{ $letter }}"
                                                        {{ in_array($letter, $normalizedAnswer, true) ? 'checked' : '' }}>
                                                    <label class="form-check-label"
                                                           for="q{{ $answer->question_id }}{{ $letter }}">
                                                        <strong>{{ $letter }}.</strong> {{ $option }}
                                                    </label>
                                                </div>
                                            @endforeach
                                            @break

                                        @case('true_false')
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="radio"
                                                       name="q{{ $answer->question_id }}"
                                                       id="q{{ $answer->question_id }}true"
                                                       value="true"
                                                        {{ $singleAnswer === 'true' ? 'checked' : '' }}>
                                                <label class="form-check-label"
                                                       for="q{{ $answer->question_id }}true">
                                                    True
                                                </label>
                                            </div>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="radio"
                                                       name="q{{ $answer->question_id }}"
                                                       id="q{{ $answer->question_id }}false"
                                                       value="false"
                                                        {{ $singleAnswer === 'false' ? 'checked' : '' }}>
                                                <label class="form-check-label"
                                                       for="q{{ $answer->question_id }}false">
                                                    False
                                                </label>
                                            </div>
                                            @break

                                        @case('fill_blank')
                                            <label class="form-label" for="answer-{{ $answer->question_id }}">Your answer</label>
                                            <input type="text" class="form-control"
                                                   id="answer-{{ $answer->question_id }}"
                                                   name="q{{ $answer->question_id }}"
                                                  value="{{ $singleAnswer ?? '' }}"
                                                   placeholder="Your answer" autocomplete="off">
                                            @break
                                    @endswitch


                                    <div class="mt-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <button class="btn btn-sm btn-info manual-save-btn" data-question-id="{{ $answer->question_id }}">
                                            <i class="bi bi-save me-1"></i>Save Answer
                                        </button>

                                        <button class="btn btn-sm btn-warning mark-review">
                                            <i class="bi {{ $answer->is_marked_for_review ? 'bi-bookmark-fill' : 'bi-bookmark' }} me-1"></i>
                                            {{ $answer->is_marked_for_review ? 'Marked for Review' : 'Mark for Review' }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <!-- Navigation Buttons -->
                    <div class="mt-4 d-flex justify-content-between flex-wrap gap-2">
                        <button class="btn btn-outline-primary prev-question">
                            <i class="bi bi-arrow-left me-1"></i> Previous
                        </button>
                        <button class="btn btn-outline-primary next-question">
                            Next <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Exam Lobby Modal -->
    <div class="modal fade" id="examLobbyModal" tabindex="-1" aria-hidden="true" aria-labelledby="examLobbyTitle" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="examLobbyTitle">{{ $session->exam->title }} - Instructions</h5>
                </div>
                <div class="modal-body d-flex flex-column justify-content-between">
                    <!-- Instructions content -->
                    <div class="instructions-content mb-4">
                        @if($session->exam->instructions)
                            <div class="mb-4">
                                <h6 class="fw-bold">Instructions:</h6>
                                <div class="border rounded p-3 bg-light">
                                    {!! nl2br(e($session->exam->instructions)) !!}
                                </div>
                            </div>
                        @endif

                        @if($session->exam->instructions_file)
                            <div class="mb-4">
                                <h6 class="fw-bold">Instruction Document:</h6>
                                <a href="{{ route('exams.instructions', $session->exam) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                    <i class="bi bi-file-earmark-download me-1"></i> Download Instructions
                                </a>
                            </div>
                        @endif

                        <div class="alert alert-warning mt-4">
                            <h6 class="fw-bold mb-2">Important Guidelines:</h6>
                            <ul class="mb-0 ms-3">
                                <li>Stay on this tab during the exam.</li>
                                <li>Do not switch windows or exit fullscreen.</li>
                                <li>Violations will be recorded and may result in exam termination.</li>
                                <li>Save your answers frequently using the Save button.</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Footer with status and button -->
                    <div class="text-center border-top pt-3">
                        <div id="lobby-status" class="text-muted mb-3" aria-live="polite">Waiting for instructor to start the exam...</div>
                        <button class="btn btn-success btn-lg" id="proceed-exam-btn" disabled>
                            Proceed for Exam
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Exam Paused Modal -->
    <div class="modal fade" id="examPausedModal" tabindex="-1" aria-hidden="true" aria-labelledby="examPausedTitle" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="examPausedTitle">Exam Paused</h5>
                </div>
                <div class="modal-body d-flex flex-column justify-content-center align-items-center text-center" aria-live="assertive">
                    <h3 class="mb-3">Exam Paused</h3>
                    <p class="lead">You left the exam window. Please wait for the instructor to allow you to resume.</p>
                    <div id="resume-status" class="text-muted mb-4">Waiting for instructor approval...</div>
                    <button class="btn btn-primary btn-lg" id="resume-exam-btn" disabled>
                        Resume Exam
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Exam Submit Confirmation Modal -->
    <div class="modal fade" id="examSubmitModal" tabindex="-1" aria-hidden="true" aria-labelledby="examSubmitTitle" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="examSubmitTitle">Submit Exam?</h5>
                </div>
                <div class="modal-body">
                    <p>Please review before submitting. This action cannot be undone.</p>
                    <ul class="list-group">
                        <li class="list-group-item list-group-item-success d-flex justify-content-between align-items-center">
                            Answered
                            <span class="badge bg-success rounded-pill" id="submit-answered-count">0</span>
                        </li>
                        <li class="list-group-item palette-marked d-flex justify-content-between align-items-center">
                            Marked for Review
                            <span class="badge rounded-pill" id="submit-marked-count" style="background-color: #e67e22;">0</span>
                        </li>
                        <li class="list-group-item palette-unanswered d-flex justify-content-between align-items-center">
                            Not Answered
                            <span class="badge bg-danger rounded-pill" id="submit-unanswered-count">0</span>
                        </li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="cancel-submit-btn">
                        Keep Working
                    </button>
                    <button type="button" class="btn btn-success" id="confirm-submit-btn">
                        Confirm Submit
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('styles')
    <style>
        .question-palette-vertical .list-group-item {
            cursor: pointer;
            transition: all 0.2s;
        }
        .question-palette-vertical .list-group-item:hover {
            background-color: #f8f9fa;
        }
        .question-palette-vertical .list-group-item.list-group-item-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .question-palette-vertical .list-group-item.palette-marked {
            background-color: #ffe5cc;
            border-color: #ffcc99;
        }
        .question-palette-vertical .list-group-item.palette-marked .palette-marked-icon {
            color: #e67e22;
        }
        .question-palette-vertical .list-group-item.palette-unanswered {
            background-color: #f8d7da;
            border-color: #f5c2c7;
        }
        .question-palette-vertical .list-group-item.palette-unanswered .palette-unanswered-icon {
            color: #dc3545;
        }
        .question-palette-vertical .list-group-item.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        .question-palette-vertical .list-group-item.active i {
            color: white;
        }
        .fixed-top {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        #timer.text-danger {
            animation: pulse 1s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        .btn-group .btn {
            min-width: 100px;
        }
    </style>
@endpush

@push('scripts')
    @vite(['resources/js/exam-taker.js'])
@endpush
