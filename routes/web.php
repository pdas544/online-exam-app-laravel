<?php


use App\Http\Controllers\AuthController;
use App\Http\Controllers\Dashboard\StudentDashboardController;
use App\Http\Controllers\Dashboard\TeacherDashboardController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamSessionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\Teacher\LiveMonitoringController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Dashboard\AdminDashboardController;
//use App\Http\Controllers\Dashboard\TeacherDashboardController;
//use App\Http\Controllers\Dashboard\StudentDashboardController;

// Public routes
Route::get('/', [HomeController::class, 'index'])->name('home');
//Route::get('/', function () {
//    return view('welcome');
//});
// Authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register']);

});

// Protected routes
Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Admin routes - using simple auth middleware with role checks in controllers
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');


    });

//    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/teacher/dashboard', [TeacherDashboardController::class, 'index'])->name('teacher.dashboard');
    Route::get('/student/dashboard', [StudentDashboardController::class, 'index'])->name('student.dashboard');

    Route::resource('users', UserController::class);
    Route::resource('subjects', SubjectController::class);
    Route::resource('exams', ExamController::class);
    Route::resource('questions', QuestionController::class);
     Route::post('/questions/{question}/duplicate', [QuestionController::class, 'duplicate'])->name('questions.duplicate');
    // Optional: Bulk import route
    Route::get('questions/import', [QuestionController::class, 'import'])
        ->name('questions.import');
});

    
   

    // Exam management routes
    Route::middleware(['auth'])->group(function () {


        // Exam Question Management Routes
        Route::get('/exams/{exam}/questions', [ExamController::class, 'manageQuestions'])->name('exams.questions');
        Route::post('/exams/{exam}/questions', [ExamController::class, 'addQuestion'])->name('exams.questions.add');
        Route::post('/exams/{exam}/questions/bulk', [ExamController::class, 'bulkAddQuestions'])->name('exams.questions.bulk');
        Route::delete('/exams/{exam}/questions/{question}', [ExamController::class, 'removeQuestion'])->name('exams.questions.remove');

        // AJAX Routes for dynamic updates
        Route::post('/exams/{exam}/questions/reorder', [ExamController::class, 'reorderQuestions'])->name('exams.questions.reorder');
        Route::put('/exams/{exam}/questions/{question}/points', [ExamController::class, 'updatePoints'])->name('exams.questions.points');
    });

// Exam Taking Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/exam/{exam}/start', [ExamSessionController::class, 'start'])->name('exam.start');
    Route::get('/exam/session/{session}/take', [ExamSessionController::class, 'take'])->name('exam.session.take');
    Route::get('/exam/session/{session}/resume', [ExamSessionController::class, 'resume'])->name('exam.session.resume');
    Route::post('/exam/session/{session}/answer', [ExamSessionController::class, 'saveAnswer'])->name('exam.session.answer');
    Route::post('/exam/session/{session}/submit', [ExamSessionController::class, 'submit'])->name('exam.session.submit');
    Route::post('/exam/session/{session}/violation', [ExamSessionController::class, 'logViolation'])->name('exam.session.violation');
    Route::get('/exam/session/{session}/status', [ExamSessionController::class, 'status'])->name('exam.session.status');
    Route::get('/exam/session/{session}/result', [ExamSessionController::class, 'result'])->name('exam.session.result');
});

// Teacher Monitoring Routes
Route::middleware(['auth', 'teacher'])->prefix('teacher')->name('teacher.')->group(function () {
    Route::get('/monitor', [LiveMonitoringController::class, 'index'])->name('monitor');
    Route::get('/monitor/{exam}', [LiveMonitoringController::class, 'monitor'])->name('monitor.exam');
    Route::get('/monitor/{exam}/sessions', [LiveMonitoringController::class, 'getSessions'])->name('monitor.sessions');
    Route::post('/monitor/session/{session}/warn', [LiveMonitoringController::class, 'sendWarning'])->name('monitor.warn');
    Route::post('/monitor/session/{session}/end', [ExamSessionController::class, 'forceEnd'])->name('monitor.force-end');
    Route::post('/monitor/session/{session}/resume', [LiveMonitoringController::class, 'resumeSession'])->name('monitor.resume');
});

// Broadcasting Authentication Route (required for private channels with Laravel Reverb)
Route::middleware('auth')->post('/broadcasting/auth', function () {
    return Illuminate\Support\Facades\Broadcast::auth();
});


