@extends('dashboard.layouts.base', [
    'role' => auth()->user()->role,
    'title' => 'Live Monitoring: ' . $exam->title
])

@section('dashboard-main')
    <div id="alerts-container" class="position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

    <div class="container-fluid">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1">{{ $exam->title }}</h4>
                                <p class="mb-0">
                                    <i class="bi bi-people me-2"></i>
                                    Active Students: <span id="active-count">{{ $sessions->whereIn('status', ['scheduled', 'in_progress', 'paused'])->count() }}</span>
                                </p>
                            </div>
                            <div>
                                <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('teacher.dashboard') }}" class="btn btn-light btn-sm me-2">
                                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                                </a>
                                <button class="btn btn-success btn-sm me-2" id="start-exam-btn">
                                    <i class="bi bi-play-circle"></i> Start Exam
                                </button>
                                <span class="badge bg-light text-dark p-2">
                                <i class="bi bi-clock me-1"></i>
                                Duration: {{ $exam->time_limit }} minutes
                            </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Live Sessions Table -->
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-camera-video me-2"></i>Live Sessions</h5>
                        <span class="badge bg-light text-dark" id="active-count-badge">{{ $sessions->whereIn('status', ['scheduled', 'in_progress', 'paused'])->count() }} Active</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                <tr>
                                    <th>Student</th>
                                    <th>Status</th>
                                    <th>Progress</th>
                                    <th>Time Spent</th>
                                    <th>Violations</th>
                                    <th>Last Activity</th>
                                    <th>Actions</th>
                                </tr>
                                </thead>
                                <tbody id="sessions-table-body">
                                @forelse($sessions as $session)
                                    @php
                                        $answered = $session->answered_answers_count ?? 0;
                                        $total = $session->total_questions;
                                        $percentage = $total > 0 ? round(($answered / $total) * 100) : 0;
                                        $minutes = floor($session->time_spent / 60);
                                        $seconds = $session->time_spent % 60;
                                    @endphp
                                    <tr id="session-{{ $session->id }}">
                                        <td>
                                            <strong>{{ $session->student->name }}</strong>
                                            <br>
                                            <small class="text-muted">{{ $session->student->email }}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $session->status === 'in_progress' ? 'success' : ($session->status === 'paused' ? 'warning' : ($session->status === 'completed' ? 'primary' : ($session->status === 'terminated' ? 'danger' : 'secondary'))) }}" data-role="status">
                                                {{ ucfirst(str_replace('_', ' ', $session->status)) }}
                                            </span>
                                        </td>
                                        <td style="min-width: 200px;">
                                            <div class="d-flex align-items-center">
                                                <div class="progress flex-grow-1 me-2" style="height: 20px;">
                                                    <div class="progress-bar bg-info"
                                                         style="width: {{ $percentage }}%">
                                                        {{ $answered }}/{{ $total }}
                                                    </div>
                                                </div>
                                                <span>{{ $percentage }}%</span>
                                            </div>
                                        </td>
                                        <td>{{ $minutes }}:{{ str_pad($seconds, 2, '0', STR_PAD_LEFT) }}</td>
                                        <td>
                                            <span class="badge bg-{{ $session->violation_count > 0 ? 'danger' : 'success' }}"
                                                  id="violation-{{ $session->id }}">
                                                {{ $session->violation_count }}
                                            </span>
                                        </td>
                                        <td id="activity-{{ $session->id }}">
                                            {{ $session->last_activity_at?->diffForHumans() ?? 'Just now' }}
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-warning"
                                                        onclick="sendWarning({{ $session->id }}, '{{ $session->student->name }}')"
                                                        title="Send Warning"
                                                    {{ $session->status !== 'in_progress' ? 'disabled' : '' }}>
                                                    <i class="bi bi-exclamation-triangle"></i>
                                                </button>
                                                <button class="btn btn-success"
                                                        onclick="resumeSession({{ $session->id }}, '{{ $session->student->name }}')"
                                                        title="Allow Resume"
                                                    {{ $session->status !== 'paused' ? 'disabled' : '' }}>
                                                    <i class="bi bi-play-circle"></i>
                                                </button>
                                                <button class="btn btn-danger"
                                                        onclick="forceEnd({{ $session->id }}, '{{ $session->student->name }}')"
                                                        title="Force End Exam"
                                                    {{ $session->status !== 'in_progress' ? 'disabled' : '' }}>
                                                    <i class="bi bi-stop-circle"></i>
                                                </button>
                                                <button class="btn btn-info"
                                                        onclick="viewDetails({{ $session->id }})"
                                                        title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr id="empty-sessions-row">
                                        <td colspan="7" class="text-center text-muted py-4">
                                            No sessions yet. Waiting for students to start the exam.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 text-end">
                            <small class="text-muted">
                                <i class="bi bi-arrow-repeat me-1"></i>
                                Auto-refreshes every <span id="refresh-count">10</span> seconds
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(count($sessions) > 0)
            <!-- Statistics Cards -->
            <div class="row mt-4">
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title">Average Progress</h5>
                            @php
                                $avgProgress = $sessions->avg(function($s) {
                                    $answered = $s->answered_answers_count ?? 0;
                                    return $s->total_questions > 0 ? ($answered / $s->total_questions) * 100 : 0;
                                });
                            @endphp
                            <h2>{{ round($avgProgress) }}%</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Violations</h5>
                            <h2>{{ $sessions->sum('violation_count') }}</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Avg. Time Spent</h5>
                            @php
                                $avgTime = $sessions->avg('time_spent');
                                $avgMinutes = floor($avgTime / 60);
                            @endphp
                            <h2>{{ $avgMinutes }} min</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-secondary text-white">
                        <div class="card-body">
                            <h5 class="card-title">Completion Rate</h5>
                            @php
                                $completed = $sessions->where('status', 'completed')->count();
                                $total = $sessions->count();
                                $rate = $total > 0 ? round(($completed / $total) * 100) : 0;
                            @endphp
                            <h2>{{ $rate }}%</h2>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Warning Modal -->
    <div class="modal fade" id="warningModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">Send Warning</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Send warning to: <strong id="warningStudentName"></strong></p>
                    <textarea id="warningMessage" class="form-control" rows="3"
                              placeholder="Enter warning message..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="sendWarningBtn">Send Warning</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Force End Modal -->
    <div class="modal fade" id="forceEndModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Force End Exam</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to force end the exam for <strong id="forceEndStudentName"></strong>?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        This action cannot be undone. The student's exam will be terminated immediately.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="forceEndBtn">Force End Exam</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        let currentSessionId = null;
        const refreshInterval = 10;
        let countdown = refreshInterval;
        let refreshTicker = null;

        document.addEventListener('DOMContentLoaded', function () {
            setupWebSocket();
            bindActions();
            refreshSessions();
            startAutoRefresh();
        });

        function bindActions() {
            document.getElementById('start-exam-btn')?.addEventListener('click', function () {
                fetch(`/teacher/monitor/{{ $exam->id }}/start`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showAlert(`Started ${data.started} session(s).`, 'success');
                            refreshSessions();
                        }
                    });
            });

            document.getElementById('sendWarningBtn')?.addEventListener('click', function () {
                const message = document.getElementById('warningMessage').value;
                if (!message.trim()) {
                    alert('Please enter a warning message');
                    return;
                }

                fetch(`/teacher/monitor/session/${currentSessionId}/warn`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({message: message})
                })
                    .then(() => {
                        bootstrap.Modal.getInstance(document.getElementById('warningModal')).hide();
                        showAlert('Warning sent successfully', 'success');
                    });
            });

            document.getElementById('forceEndBtn')?.addEventListener('click', function () {
                fetch(`/teacher/monitor/session/${currentSessionId}/end`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                })
                    .then(() => {
                        bootstrap.Modal.getInstance(document.getElementById('forceEndModal')).hide();
                        refreshSessions();
                        showAlert('Exam ended successfully', 'danger');
                    });
            });
        }

        function setupWebSocket() {
            if (!window.Echo) {
                return;
            }

            window.Echo.channel(`exam.{{ $exam->id }}`)
                .listen('.student.joined', () => {
                    refreshSessions();
                })
                .listen('.exam.ended', () => {
                    refreshSessions();
                })
                .listen('.exam.start.allowed', () => {
                    refreshSessions();
                })
                .listen('.violation.detected', (eventData) => {
                    showViolationAlert(eventData);
                    refreshSessions();
                });

            window.Echo.channel(`teacher.{{ Auth::id() }}`)
                .listen('.student.joined', () => {
                    refreshSessions();
                })
                .listen('.exam.ended', () => {
                    refreshSessions();
                });
        }

        function startAutoRefresh() {
            if (refreshTicker) {
                clearInterval(refreshTicker);
            }

            refreshTicker = setInterval(function () {
                countdown -= 1;

                const refreshCountEl = document.getElementById('refresh-count');
                if (refreshCountEl) {
                    refreshCountEl.textContent = String(countdown);
                }

                if (countdown <= 0) {
                    refreshSessions();
                    countdown = refreshInterval;
                }
            }, 1000);
        }

        function refreshSessions() {
            fetch(`/teacher/monitor/{{ $exam->id }}/sessions`)
                .then(response => response.json())
                .then(data => {
                    updateTable(data.sessions || []);

                    const activeCount = Number(data.total_active || 0);
                    const activeCountEl = document.getElementById('active-count');
                    const activeCountBadgeEl = document.getElementById('active-count-badge');

                    if (activeCountEl) {
                        activeCountEl.textContent = String(activeCount);
                    }

                    if (activeCountBadgeEl) {
                        activeCountBadgeEl.textContent = `${activeCount} Active`;
                    }
                })
                .finally(() => {
                    countdown = refreshInterval;
                });
        }

        function updateTable(sessions) {
            const tbody = document.getElementById('sessions-table-body');
            if (!tbody) {
                return;
            }

            if (!sessions.length) {
                tbody.innerHTML = `
                    <tr id="empty-sessions-row">
                        <td colspan="7" class="text-center text-muted py-4">
                            No sessions yet. Waiting for students to start the exam.
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = sessions.map(buildSessionRow).join('');
        }

        function buildSessionRow(session) {
            const status = String(session.status || 'scheduled');
            const displayStatus = status.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
            const statusClass = getStatusClass(status);
            const progress = Number(session.progress || 0);
            const total = Number(session.total || 0);
            const percentage = total > 0 ? Math.round((progress / total) * 100) : 0;
            const violations = Number(session.violations || 0);
            const timeSpent = Number(session.time_spent || 0);
            const minutes = Math.floor(timeSpent / 60);
            const seconds = timeSpent % 60;
            const canWarn = status === 'in_progress';
            const canResume = status === 'paused';
            const canForceEnd = status === 'in_progress';
            const safeName = escapeJsText(session.student_name || 'Student');

            return `
                <tr id="session-${session.id}">
                    <td>
                        <strong>${escapeHtml(session.student_name || 'Student')}</strong>
                        <br>
                        <small class="text-muted">${escapeHtml(session.student_email || '')}</small>
                    </td>
                    <td>
                        <span class="badge bg-${statusClass}" data-role="status">${displayStatus}</span>
                    </td>
                    <td style="min-width: 200px;">
                        <div class="d-flex align-items-center">
                            <div class="progress flex-grow-1 me-2" style="height: 20px;">
                                <div class="progress-bar bg-info" style="width: ${percentage}%">
                                    ${progress}/${total}
                                </div>
                            </div>
                            <span>${percentage}%</span>
                        </div>
                    </td>
                    <td>${minutes}:${String(seconds).padStart(2, '0')}</td>
                    <td>
                        <span class="badge bg-${violations > 0 ? 'danger' : 'success'}" id="violation-${session.id}">
                            ${violations}
                        </span>
                    </td>
                    <td id="activity-${session.id}">${escapeHtml(session.last_activity || 'Just now')}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-warning" onclick="sendWarning(${session.id}, '${safeName}')" title="Send Warning" ${canWarn ? '' : 'disabled'}>
                                <i class="bi bi-exclamation-triangle"></i>
                            </button>
                            <button class="btn btn-success" onclick="resumeSession(${session.id}, '${safeName}')" title="Allow Resume" ${canResume ? '' : 'disabled'}>
                                <i class="bi bi-play-circle"></i>
                            </button>
                            <button class="btn btn-danger" onclick="forceEnd(${session.id}, '${safeName}')" title="Force End Exam" ${canForceEnd ? '' : 'disabled'}>
                                <i class="bi bi-stop-circle"></i>
                            </button>
                            <button class="btn btn-info" onclick="viewDetails(${session.id})" title="View Details">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }

        function getStatusClass(status) {
            if (status === 'in_progress') return 'success';
            if (status === 'paused') return 'warning';
            if (status === 'completed') return 'primary';
            if (status === 'terminated') return 'danger';
            return 'secondary';
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function escapeJsText(value) {
            return String(value || '')
                .replace(/\\/g, '\\\\')
                .replace(/'/g, "\\'")
                .replace(/\n/g, ' ')
                .replace(/\r/g, ' ');
        }

        function showViolationAlert(eventData) {
            const alertDiv = document.createElement('div');
            const severity = Number(eventData.severity || 1);
            const style = severity >= 3 ? 'danger' : 'warning';

            alertDiv.className = `alert alert-${style} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                <strong>${escapeHtml(eventData.student_name || 'Student')}</strong> -
                ${escapeHtml(eventData.violation_type || 'violation')} (Severity: ${severity})
                <br>${escapeHtml(eventData.description || '')}
                ${eventData.auto_terminated ? '<br><span class="fw-bold">Session auto-terminated.</span>' : ''}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.getElementById('alerts-container')?.prepend(alertDiv);
            setTimeout(() => alertDiv.remove(), 8000);
        }

        function sendWarning(sessionId, studentName) {
            currentSessionId = sessionId;
            document.getElementById('warningStudentName').textContent = studentName;
            document.getElementById('warningMessage').value = '';

            const modal = new bootstrap.Modal(document.getElementById('warningModal'));
            modal.show();
        }

        function forceEnd(sessionId, studentName) {
            currentSessionId = sessionId;
            document.getElementById('forceEndStudentName').textContent = studentName;

            const modal = new bootstrap.Modal(document.getElementById('forceEndModal'));
            modal.show();
        }

        function resumeSession(sessionId, studentName) {
            if (!confirm(`Allow ${studentName} to resume the exam?`)) {
                return;
            }

            fetch(`/teacher/monitor/session/${sessionId}/resume`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            }).then(() => {
                refreshSessions();
                showAlert('Resume allowed for student', 'success');
            });
        }

        function viewDetails(sessionId) {
            window.location.href = `/teacher/monitor/session/${sessionId}/details`;
        }

        function showAlert(message, type = 'success') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.getElementById('alerts-container')?.appendChild(alertDiv);
            setTimeout(() => alertDiv.remove(), 5000);
        }
    </script>
@endpush
