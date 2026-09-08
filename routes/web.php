<?php

use App\Http\Controllers\Admin\MetricsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Dashboard\AdminDashboardController;
use App\Http\Controllers\Dashboard\StudentDashboardController;
use App\Http\Controllers\Dashboard\TeacherDashboardController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamSessionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\Teacher\LiveMonitoringController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/', [HomeController::class, 'index'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');

    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
});

// Everything below requires authentication.
Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Admin routes (role checks live in controllers)
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::get('/metrics', [MetricsController::class, 'show'])->name('metrics');
        Route::get('/exam-sessions/active', [AdminDashboardController::class, 'activeSessions'])->name('exam-sessions.active');
    });

    // Role dashboards
    Route::get('/teacher/dashboard', [TeacherDashboardController::class, 'index'])->name('teacher.dashboard');
    Route::get('/student/dashboard', [StudentDashboardController::class, 'index'])->name('student.dashboard');
    Route::get('/student/results', [StudentDashboardController::class, 'results'])->name('student.results.index');
    Route::get('/student/results/{session}', [StudentDashboardController::class, 'showResult'])->name('student.results.show');

    // Resource CRUD
    Route::resource('users', UserController::class);
    Route::resource('subjects', SubjectController::class);
    Route::resource('exams', ExamController::class);
    // Custom question routes must be defined BEFORE the resource so they
    // are not shadowed by `questions/{question}` (show).
    Route::get('questions/import', [QuestionController::class, 'import'])->name('questions.import');
    Route::resource('questions', QuestionController::class);
    Route::post('/questions/{question}/duplicate', [QuestionController::class, 'duplicate'])->name('questions.duplicate');

    // Exam question management
    Route::get('/exams/{exam}/questions', [ExamController::class, 'manageQuestions'])->name('exams.questions');
    Route::get('/exams/{exam}/instructions', [ExamController::class, 'downloadInstructions'])->name('exams.instructions');
    Route::post('/exams/{exam}/questions', [ExamController::class, 'addQuestion'])->name('exams.questions.add');
    Route::post('/exams/{exam}/questions/bulk', [ExamController::class, 'bulkAddQuestions'])->name('exams.questions.bulk');
    Route::delete('/exams/{exam}/questions/{question}', [ExamController::class, 'removeQuestion'])->name('exams.questions.remove');

    // AJAX routes for dynamic updates
    Route::post('/exams/{exam}/questions/reorder', [ExamController::class, 'reorderQuestions'])->name('exams.questions.reorder');
    Route::put('/exams/{exam}/questions/{question}/points', [ExamController::class, 'updatePoints'])->name('exams.questions.points');

    // Exam taking routes (rate-limited against answer/violation spam)
    Route::middleware(['throttle:exam'])->group(function () {
        Route::get('/exam/{exam}/start', [ExamSessionController::class, 'start'])->name('exam.start');
        Route::get('/exam/session/{session}/take', [ExamSessionController::class, 'take'])->name('exam.session.take');
        Route::post('/exam/session/{session}/begin', [ExamSessionController::class, 'begin'])->name('exam.session.begin');
        Route::get('/exam/session/{session}/resume', [ExamSessionController::class, 'resume'])->name('exam.session.resume');
        Route::post('/exam/session/{session}/answer', [ExamSessionController::class, 'saveAnswer'])->name('exam.session.answer');
        Route::post('/exam/session/{session}/submit', [ExamSessionController::class, 'submit'])->name('exam.session.submit');
        Route::post('/exam/session/{session}/violation', [ExamSessionController::class, 'logViolation'])->name('exam.session.violation');
        Route::get('/exam/session/{session}/status', [ExamSessionController::class, 'status'])->name('exam.session.status');
        Route::get('/exam/session/{session}/result', [ExamSessionController::class, 'result'])->name('exam.session.result');
    });

    // Teacher monitoring routes (teacher middleware allows teacher OR admin)
    Route::middleware(['teacher'])->prefix('teacher')->name('teacher.')->group(function () {
        Route::get('/monitor', [LiveMonitoringController::class, 'index'])->name('monitor');
        Route::get('/monitor/{exam}', [LiveMonitoringController::class, 'monitor'])->name('monitor.exam');
        Route::get('/monitor/{exam}/sessions', [LiveMonitoringController::class, 'getSessions'])->name('monitor.sessions');
        Route::post('/monitor/{exam}/start', [LiveMonitoringController::class, 'startExam'])->name('monitor.start');
        Route::get('/monitor/session/{session}/details', [LiveMonitoringController::class, 'showSession'])->name('monitor.details');
        Route::post('/monitor/session/{session}/warn', [LiveMonitoringController::class, 'sendWarning'])->name('monitor.warn');
        Route::post('/monitor/session/{session}/end', [ExamSessionController::class, 'forceEnd'])->name('monitor.force-end');
        Route::post('/monitor/session/{session}/resume', [LiveMonitoringController::class, 'resumeSession'])->name('monitor.resume');
    });
});

// Broadcast auth (`/broadcasting/auth`) + `routes/channels.php` are wired
// via `channels:` in `bootstrap/app.php` — do not redeclare here.
