class ExamTaker {
    constructor(sessionId, examId, config) {
        this.debug = config.debug || false;
        
        if (this.debug) {
            console.log('ExamTaker constructor called with:', { sessionId, examId, config });
        }

        this.sessionId = sessionId;
        this.examId = examId;
        this.config = config;
        this.autoSaveInterval = null;
        this.timerInterval = null;
        this.violationCount = 0;
        this.lastActivity = Date.now();
        this.currentQuestionIndex = 0;
        this.totalQuestions = document.querySelectorAll('.question-card').length;
        this.paused = false;
        this.resumeAllowed = false;
        this.pauseModal = null;

        console.log('Total questions found:', this.totalQuestions);

        if (this.totalQuestions === 0) {
            console.error('No questions found! Check if .question-card elements exist');
        }

        this.init();
    }

    init() {
        console.log('%c📝 Exam Taker Initialized', 'color: purple; font-size: 14px; font-weight: bold');
        console.log('Session ID:', this.sessionId);
        console.log('Exam ID:', this.examId);
        console.log('Total Questions:', this.totalQuestions);
        console.log('Auto-save Interval:', this.config.autoSaveInterval, 'seconds');
        console.log('----------------------------------------');

        this.checkElements();
        this.setupEventListeners();
        this.setupManualSave(); // Add this
        this.setupAutoSave();
        this.setupPauseModal();
        this.setupViolationDetection();
        this.setupWebSocket();
        this.setupBeforeUnload(); // Add this
        this.startTimer();

        if (this.totalQuestions > 0) {
            this.showQuestion(0);
        }
    }

    checkElements() {
        console.log('Checking for required elements:');
        console.log('- Question cards:', document.querySelectorAll('.question-card').length);
        console.log('- Submit button:', document.getElementById('submit-exam') ? 'Found' : 'Not found');
        console.log('- Timer element:', document.getElementById('timer') ? 'Found' : 'Not found');
        console.log('- Previous button:', document.querySelector('.prev-question') ? 'Found' : 'Not found');
        console.log('- Next button:', document.querySelector('.next-question') ? 'Found' : 'Not found');
        console.log('- Navigation buttons:', document.querySelectorAll('.nav-question').length);
    }

    setupEventListeners() {
        console.log('Setting up event listeners...');

        // Save on option change
        const radioInputs = document.querySelectorAll('input[type=radio]');
        const checkboxInputs = document.querySelectorAll('input[type=checkbox]');
        console.log(`Found ${radioInputs.length} radio inputs, ${checkboxInputs.length} checkbox inputs`);

        radioInputs.forEach(input => {
            input.addEventListener('change', () => this.saveAnswer(input));
        });

        checkboxInputs.forEach(input => {
            input.addEventListener('change', () => this.saveAnswer(input));
        });

        // Save on text input (with debounce)
        const textInputs = document.querySelectorAll('input[type=text], textarea');
        console.log(`Found ${textInputs.length} text inputs`);

        textInputs.forEach(input => {
            input.addEventListener('input', this.debounce(() => this.saveAnswer(input), 1000));
        });

        // Mark for review
        const markReviewBtns = document.querySelectorAll('.mark-review');
        console.log(`Found ${markReviewBtns.length} mark review buttons`);

        markReviewBtns.forEach(btn => {
            btn.addEventListener('click', (e) => this.toggleMarkForReview(e));
        });

        // Navigation buttons
        const prevBtn = document.querySelector('.prev-question');
        const nextBtn = document.querySelector('.next-question');

        if (prevBtn) {
            console.log('Previous button found, attaching listener');
            prevBtn.addEventListener('click', () => this.previousQuestion());
        } else {
            console.warn('Previous button not found');
        }

        if (nextBtn) {
            console.log('Next button found, attaching listener');
            nextBtn.addEventListener('click', () => this.nextQuestion());
        } else {
            console.warn('Next button not found');
        }

        // Palette navigation
        const navButtons = document.querySelectorAll('.nav-question');
        console.log(`Found ${navButtons.length} palette navigation buttons`);

        navButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const index = parseInt(e.currentTarget.dataset.index);
                console.log(`Palette navigation clicked for index: ${index}`);
                this.showQuestion(index);
            });
        });

        // Submit exam
        const submitBtn = document.getElementById('submit-exam');
        if (submitBtn) {
            console.log('Submit button found, attaching listener');
            submitBtn.addEventListener('click', () => this.submitExam());
        } else {
            console.warn('Submit button not found');
        }

        console.log('Event listeners setup complete');
    }

    showQuestion(index) {
        console.log(`showQuestion called with index: ${index}`);

        if (index < 0 || index >= this.totalQuestions) {
            console.warn(`Invalid index: ${index}, total questions: ${this.totalQuestions}`);
            return;
        }

        // Hide all questions
        document.querySelectorAll('.question-card').forEach(card => {
            card.classList.add('d-none');
        });

        // Show selected question
        const selectedCard = document.querySelector(`.question-card[data-index="${index}"]`);
        if (selectedCard) {
            selectedCard.classList.remove('d-none');
            this.currentQuestionIndex = index;
            console.log(`Showing question ${index + 1}`);
        } else {
            console.error(`Question card with data-index="${index}" not found`);
        }

        // Update active state in palette
        document.querySelectorAll('.nav-question').forEach(btn => {
            btn.classList.remove('active');
        });

        const activePaletteBtn = document.querySelector(`.nav-question[data-index="${index}"]`);
        if (activePaletteBtn) {
            activePaletteBtn.classList.add('active');
        }

        // Update navigation buttons
        const prevBtn = document.querySelector('.prev-question');
        const nextBtn = document.querySelector('.next-question');

        if (prevBtn) {
            prevBtn.disabled = index === 0;
        }
        if (nextBtn) {
            nextBtn.disabled = index === this.totalQuestions - 1;
        }

        console.log(`🔍 Navigated to question ${index + 1}`);
    }

    previousQuestion() {
        console.log(`previousQuestion called, current index: ${this.currentQuestionIndex}`);
        if (this.currentQuestionIndex > 0) {
            this.showQuestion(this.currentQuestionIndex - 1);
        }
    }

    nextQuestion() {
        console.log(`nextQuestion called, current index: ${this.currentQuestionIndex}`);
        if (this.currentQuestionIndex < this.totalQuestions - 1) {
            this.showQuestion(this.currentQuestionIndex + 1);
        }
    }

    saveAnswer(input) {
        const questionCard = input.closest('.question-card');
        if (!questionCard) {
            console.error('Could not find question card for input');
            return;
        }

        const questionId = questionCard.dataset.questionId;
        let answer = null;

        if (input.type === 'radio') {
            if (input.checked) {
                answer = input.value;
            }
        } else if (input.type === 'checkbox') {
            const name = input.name;
            const checked = document.querySelectorAll(`input[name="${name}"]:checked`);
            answer = Array.from(checked).map(cb => cb.value);
        } else {
            answer = input.value;
        }

        console.log(`💾 Saving Answer - Question: ${questionId}, Answer:`, answer);

        const data = {
            question_id: questionId,
            answer: answer,
            _token: this.config.csrf
        };

        fetch(`/exam/session/${this.sessionId}/answer`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.config.csrf
            },
            body: JSON.stringify(data)
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Save answer response:', data);
                if (data.success) {
                    this.updateProgress(data.progress);
                    this.updateQuestionStatus(questionId, true);
                }
            })
            .catch(error => {
                console.error('Error saving answer:', error);
            });
    }

    toggleMarkForReview(e) {
        const btn = e.currentTarget;
        const questionCard = btn.closest('.question-card');
        if (!questionCard) {
            console.error('Could not find question card for mark review button');
            return;
        }

        const questionId = questionCard.dataset.questionId;
        const isMarked = btn.classList.contains('marked');

        console.log(`📌 Toggling mark for review - Question: ${questionId}, Currently marked: ${isMarked}`);

        const data = {
            question_id: questionId,
            is_marked_for_review: !isMarked,
            _token: this.config.csrf
        };

        fetch(`/exam/session/${this.sessionId}/answer`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.config.csrf
            },
            body: JSON.stringify(data)
        })
            .then(response => response.json())
            .then(data => {
                console.log('Mark review response:', data);
                if (data.success) {
                    btn.classList.toggle('marked');
                    btn.innerHTML = isMarked ?
                        '<i class="bi bi-bookmark me-1"></i>Mark for Review' :
                        '<i class="bi bi-bookmark-fill me-1"></i>Marked for Review';

                    this.updateQuestionStatus(questionId, null, !isMarked);
                }
            })
            .catch(error => {
                console.error('Error toggling mark for review:', error);
            });
    }

    updateQuestionStatus(questionId, isAnswered = null, isMarked = null) {
        const paletteBtn = document.querySelector(`.nav-question[data-target="${questionId}"]`);
        if (!paletteBtn) {
            console.warn(`Palette button for question ${questionId} not found`);
            return;
        }

        const icon = paletteBtn.querySelector('i');
        const questionCard = document.querySelector(`#question-${questionId}`);

        if (questionCard && isAnswered !== null) {
            questionCard.dataset.answered = isAnswered ? 'true' : 'false';
        }

        // Update icon based on current status
        const currentIsAnswered = questionCard ? questionCard.dataset.answered === 'true' : false;
        const currentIsMarked = isMarked !== null ? isMarked :
            (paletteBtn.querySelector('.bi-bookmark-fill, .bi-bookmark-check-fill') !== null);

        if (currentIsAnswered && currentIsMarked) {
            icon.className = 'bi bi-bookmark-check-fill';
            paletteBtn.classList.add('list-group-item-warning');
            paletteBtn.classList.remove('list-group-item-success');
        } else if (currentIsAnswered) {
            icon.className = 'bi bi-check-circle-fill text-success';
            paletteBtn.classList.add('list-group-item-success');
            paletteBtn.classList.remove('list-group-item-warning');
        } else if (currentIsMarked) {
            icon.className = 'bi bi-bookmark-fill text-warning';
            paletteBtn.classList.add('list-group-item-warning');
            paletteBtn.classList.remove('list-group-item-success');
        } else {
            icon.className = 'bi bi-circle text-secondary';
            paletteBtn.classList.remove('list-group-item-success', 'list-group-item-warning');
        }
    }

    autoSave() {
        console.log(`⏰ Auto-save triggered at ${new Date().toLocaleTimeString()}`);

        const currentCard = document.querySelector('.question-card:not(.d-none)');
        if (!currentCard) {
            console.log('No current question card found for auto-save');
            return;
        }

        const inputs = currentCard.querySelectorAll('input[type=text], textarea');
        inputs.forEach(input => {
            if (input.value) {
                this.saveAnswer(input);
            }
        });
    }

    setupAutoSave() {
        console.log(`Setting up auto-save every ${this.config.autoSaveInterval} seconds`);
        this.autoSaveInterval = setInterval(() => {
            this.autoSave();
        }, this.config.autoSaveInterval * 1000);
    }

    setupPauseModal() {
        const modalEl = document.getElementById('examPausedModal');
        if (!modalEl || !window.bootstrap) return;

        this.pauseModal = new window.bootstrap.Modal(modalEl, {
            backdrop: 'static',
            keyboard: false,
        });

        const resumeBtn = document.getElementById('resume-exam-btn');
        if (resumeBtn) {
            resumeBtn.addEventListener('click', () => {
                if (!this.resumeAllowed) return;
                this.resumeExam();
            });
        }
    }

    handleFocusLoss(type, description) {
        if (this.paused) return;
        this.paused = true;
        this.resumeAllowed = false;

        // Stop timers while paused
        this.cleanup();

        // Show pause modal
        if (this.pauseModal) {
            const statusEl = document.getElementById('resume-status');
            const resumeBtn = document.getElementById('resume-exam-btn');
            if (statusEl) statusEl.textContent = 'Waiting for instructor approval...';
            if (resumeBtn) resumeBtn.disabled = true;
            this.pauseModal.show();
        }

        this.logViolation(type, description);
    }

    enableResume(message = 'Resume allowed') {
        this.resumeAllowed = true;
        const statusEl = document.getElementById('resume-status');
        const resumeBtn = document.getElementById('resume-exam-btn');
        if (statusEl) statusEl.textContent = message;
        if (resumeBtn) resumeBtn.disabled = false;
    }

    resumeExam() {
        if (!this.resumeAllowed) return;
        this.paused = false;

        if (this.pauseModal) {
            this.pauseModal.hide();
        }

        // Restart timers
        this.setupAutoSave();
        this.startTimer();
    }

    setupViolationDetection() {
        console.log('Setting up violation detection');

        // Tab switching
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                console.log('Tab switch detected');
                this.handleFocusLoss('tab_switch', 'Student switched tabs');
            }
        });

        // Window blur
        window.addEventListener('blur', () => {
            console.log('Window blur detected');
            this.handleFocusLoss('window_blur', 'Window lost focus');
        });

        // Fullscreen detection
        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement) {
                console.log('Fullscreen exit detected');
                this.handleFocusLoss('fullscreen_exit', 'Exited fullscreen mode');
                this.reenableFullscreen();
            }
        });

        // Keyboard tab key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Tab') {
                console.log('Tab key detected');
                this.handleFocusLoss('tab_key', 'Pressed tab key');
            }
        });

        // Copy/Paste prevention
        document.addEventListener('copy', (e) => {
            e.preventDefault();
            console.log('Copy attempt detected');
            this.logViolation('copy_attempt', 'Attempted to copy');
        });

        document.addEventListener('paste', (e) => {
            e.preventDefault();
            console.log('Paste attempt detected');
            this.logViolation('paste_attempt', 'Attempted to paste');
        });

        // Right-click prevention
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
        });
    }

    logViolation(type, description) {
        console.log(`⚠️ Violation Detected - Type: ${type}, Description: ${description}`);

        const data = {
            type: type,
            description: description,
            metadata: {
                url: window.location.href,
                timestamp: new Date().toISOString()
            },
            _token: this.config.csrf
        };

        fetch(`/exam/session/${this.sessionId}/violation`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.config.csrf
            },
            body: JSON.stringify(data)
        })
            .then(response => {
                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // If not JSON, it might be a redirect (HTML)
                    console.warn('Non-JSON response received, possibly a redirect');
                    return { success: false, redirected: true };
                }
            })
            .then(data => {
                console.log('Violation log response:', data);
                if (data.terminated) {
                    alert('Exam terminated due to multiple violations.');
                    window.location.href = data.redirect || '/dashboard';
                } else if (data.warning) {
                    this.showWarning(data.warning);
                }
            })
            .catch(error => {
                console.error('Error logging violation:', error);
            });
    }

    reenableFullscreen() {
        if (document.documentElement.requestFullscreen) {
            document.documentElement.requestFullscreen();
        }
    }

    setupWebSocket() {
        console.log('Setting up WebSocket connection');

        if (!window.Echo) {
            console.error('Echo not initialized');
            return;
        }

        // Listen for teacher warnings
        window.Echo.private(`exam.${this.examId}`)
            .listen('.teacher.warning', (e) => {
                console.log('Teacher warning received:', e);
                this.showWarning(e.message);
            })
            .listen('.exam.forceEnd', () => {
                console.log('Force end command received from teacher');
                this.forceEndExam();
            });

        // Listen for resume permission on student channel
        if (this.config.studentId) {
            window.Echo.private(`student.${this.config.studentId}`)
                .listen('.exam.resume', (e) => {
                    console.log('Resume allowed by instructor:', e);
                    this.enableResume(e.message || 'Resume allowed');
                });
        }
    }

    startTimer() {
        if (this.debug) console.log('Starting timer...');

        const timerElement = document.getElementById('timer');
        if (!timerElement) {
            console.error('Timer element not found');
            return;
        }

        let remainingSeconds = null;

        // Update display
        const updateDisplay = () => {
            if (remainingSeconds === null || remainingSeconds < 0) {
                return;
            }

            // Decrement first
            remainingSeconds--;

            // Then calculate and display
            const minutes = Math.floor(remainingSeconds / 60);
            const seconds = Math.floor(remainingSeconds % 60);
            timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

            // Warning colors
            if (remainingSeconds <= 300) { // 5 minutes
                timerElement.classList.add('text-danger', 'fw-bold');
            }

            // Auto-submit when time is up
            if (remainingSeconds <= 0) {
                clearInterval(this.timerInterval);
                this.autoSubmit();
                return;
            }
        };

        // Fetch initial time from server once
        fetch(`/exam/session/${this.sessionId}/status`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // Ensure we have an integer value
                remainingSeconds = Math.floor(parseInt(data.time_remaining, 10));
                
                if (this.debug) {
                    console.log(`⏱️ Initial time: ${remainingSeconds} seconds remaining`);
                }

                // Display initial time immediately
                const minutes = Math.floor(remainingSeconds / 60);
                const seconds = Math.floor(remainingSeconds % 60);
                timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

                // Start countdown every second
                this.timerInterval = setInterval(updateDisplay, 1000);
            })
            .catch(error => {
                console.error('Error fetching timer status:', error);
            });
    }

    updateProgress(progress) {
        console.log('Updating progress:', progress);

        const answeredCount = document.getElementById('answered-count');
        const progressBar = document.getElementById('progress-bar');

        if (answeredCount) {
            answeredCount.textContent = progress.answered;
        }

        if (progressBar) {
            progressBar.style.width = `${(progress.answered / progress.total) * 100}%`;
        }
    }

    showWarning(message) {
        const warningDiv = document.createElement('div');
        warningDiv.className = 'alert alert-warning alert-dismissible fade show position-fixed top-0 end-0 m-3';
        warningDiv.style.zIndex = '9999';
        warningDiv.innerHTML = `
            <strong>Warning:</strong> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(warningDiv);

        setTimeout(() => warningDiv.remove(), 5000);
    }

    submitExam() {
            if (this.debug) console.log('Submit exam called');

            if (!confirm('Are you sure you want to submit your exam? This action cannot be undone.')) {
                return;
            }

            // Clean up intervals
            this.cleanup();

            // Show loading state
            const submitBtn = document.getElementById('submit-exam');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';

            fetch(`/exam/session/${this.sessionId}/submit`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.config.csrf,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({}) // Send empty object if needed
            })
                .then(async response => {
                    console.log('Submit response status:', response.status);

                    // Try to parse as JSON first
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        const data = await response.json();
                        if (response.ok) {
                            if (data.redirect) {
                                window.location.href = data.redirect;
                            } else {
                                window.location.href = '/student/dashboard';
                            }
                        } else {
                            throw new Error(data.error || 'Submit failed');
                        }
                    } else {
                        // Not JSON - might be a redirect
                        if (response.redirected) {
                            window.location.href = response.url;
                        } else {
                            const text = await response.text();
                            console.error('Non-JSON response:', text.substring(0, 200));
                            throw new Error('Server returned non-JSON response');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error submitting exam:', error);
                    alert('Failed to submit exam: ' + error.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                });
        }


    // Add manual save button functionality
    setupManualSave() {
        // Use event delegation for multiple save buttons
        document.addEventListener('click', (e) => {
            if (e.target.closest('.manual-save-btn')) {
                if (this.debug) console.log('Manual save triggered');
                const btn = e.target.closest('.manual-save-btn');
                const questionId = btn.dataset.questionId;
                const questionCard = document.querySelector(`[data-question-id="${questionId}"]`);
                
                if (questionCard) {
                    const inputs = questionCard.querySelectorAll('input:checked, input[type="text"], textarea');
                    inputs.forEach(input => {
                        if (input.value || input.checked) {
                            this.saveAnswer(input);
                        }
                    });
                    
                    // Show success message
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-dismissible fade show position-fixed top-0 end-0 m-3';
                    alertDiv.style.zIndex = '9999';
                    alertDiv.innerHTML = `
                        <i class="bi bi-check-circle me-2"></i>Answer saved!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.body.appendChild(alertDiv);
                    setTimeout(() => alertDiv.remove(), 2000);
                }
            }
        });
    }

    // Handle page unload (pause exam)
    setupBeforeUnload() {
        window.addEventListener('beforeunload', (e) => {
            if (this.sessionId && this.config.autoSaveInterval) {
                // Auto-save current state
                const currentCard = document.querySelector('.question-card:not(.d-none)');
                if (currentCard) {
                    const inputs = currentCard.querySelectorAll('input, textarea');
                    inputs.forEach(input => {
                        if (input.value || input.checked) {
                            this.saveAnswer(input);
                        }
                    });
                }

                // Show confirmation message
                e.preventDefault();
                e.returnValue = 'Your exam is in progress. Are you sure you want to leave?';
            }
        });
    }
    cleanup() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
            this.timerInterval = null;
        }
        if (this.autoSaveInterval) {
            clearInterval(this.autoSaveInterval);
            this.autoSaveInterval = null;
        }
    }

    autoSubmit() {
        this.cleanup();
        alert('Time is up! Your exam will be submitted automatically.');
        this.submitExam();
    }

    forceEndExam() {
        this.cleanup();
        alert('Your exam has been ended by the teacher.');
        window.location.reload();
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM fully loaded, checking for exam container...');

    const examContainer = document.getElementById('exam-container');
    if (examContainer) {
        console.log('Exam container found, initializing ExamTaker');
        console.log('Session ID:', examContainer.dataset.sessionId);
        console.log('Exam ID:', examContainer.dataset.examId);
        console.log('Config:', examContainer.dataset.config);

        try {
            new ExamTaker(
                examContainer.dataset.sessionId,
                examContainer.dataset.examId,
                JSON.parse(examContainer.dataset.config)
            );
        } catch (error) {
            console.error('Error initializing ExamTaker:', error);
        }
    } else {
        console.log('No exam container found on this page');
    }
});
