class TeacherMonitor {
    constructor(examId, config = {}) {
    this.examId = examId;
    this.config = config;
    this.sessions = [];
    this.init();
}

    init() {
        this.setupWebSocket();
        this.startPolling();
    }

    setupWebSocket() {
        window.Echo.private(`teacher.${this.config.teacherId}`)
            .listen('.exam.started', (e) => {
                this.addStudent(e);
            })
            .listen('.exam.ended', (e) => {
                this.removeStudent(e);
            })
            .listen('.violation.detected', (e) => {
                this.showViolationAlert(e);
            });
    }

    startPolling() {
        setInterval(() => {
            this.fetchSessions();
        }, 5000); // Every 5 seconds
    }

    fetchSessions() {
        fetch(`/teacher/monitor/${this.examId}/sessions`)
            .then(response => response.json())
            .then(data => {
                this.updateTable(data.sessions);
                document.getElementById('active-count').textContent = data.total_active;
            });
    }

    updateTable(sessions) {
        const tbody = document.getElementById('sessions-table-body');
        if (!tbody) return;

        tbody.innerHTML = sessions.map(session => `
            <tr>
                <td>${session.student_name}</td>
                <td>
                    <span class="badge bg-${session.status === 'in_progress' ? 'success' : 'warning'}">
                        ${session.status}
                    </span>
                </td>
                <td>
                    <div class="progress" style="height: 20px;">
                        <div class="progress-bar" style="width: ${(session.progress / session.total) * 100}%">
                            ${session.progress}/${session.total}
                        </div>
                    </div>
                </td>
                <td>${Math.floor(session.time_spent / 60)}:${(session.time_spent % 60).toString().padStart(2, '0')}</td>
                <td>
                    <span class="badge bg-${session.violations > 0 ? 'danger' : 'success'}">
                        ${session.violations}
                    </span>
                </td>
                <td>${session.last_activity}</td>
                <td>
                    <button class="btn btn-sm btn-warning" onclick="sendWarning(${session.id})">
                        Warn
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="forceEnd(${session.id})">
                        End
                    </button>
                </td>
            </tr>
        `).join('');
    }

    sendWarning(sessionId) {
        const message = prompt('Enter warning message:');
        if (!message) return;

        fetch(`/teacher/monitor/session/${sessionId}/warn`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ message })
        });
    }

    forceEnd(sessionId) {
        if (!confirm('Are you sure you want to force end this exam?')) return;

        fetch(`/teacher/monitor/session/${sessionId}/end`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
            .then(() => {
                this.fetchSessions();
            });
    }

    showViolationAlert(e) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${e.severity >= 3 ? 'danger' : 'warning'} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            <strong>${e.student_name}</strong> - ${e.violation_type} (Severity: ${e.severity})
            <br>${e.description}
            ${e.auto_terminated ? '<br><span class="fw-bold">Session auto-terminated!</span>' : ''}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.getElementById('alerts-container').prepend(alertDiv);
    }
}
