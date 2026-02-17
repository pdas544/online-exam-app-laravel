<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\SubjectController;
use Illuminate\Support\Facades\Route;

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
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');


    });

    Route::resource('users', UserController::class);
    Route::resource('subjects', SubjectController::class);
    Route::resource('exams', ExamController::class);
    Route::resource('questions', QuestionController::class);
    Route::post('/questions/{question}/duplicate', [QuestionController::class, 'duplicate'])->name('questions.duplicate');
    // Optional: Bulk import route
    Route::get('questions/import', [QuestionController::class, 'import'])
        ->name('questions.import');

    // Exam management routes
    Route::middleware(['auth'])->group(function () {
        Route::resource('exams', ExamController::class);

        // Exam Question Management Routes
        Route::get('/exams/{exam}/questions', [ExamController::class, 'manageQuestions'])->name('exams.questions');
        Route::post('/exams/{exam}/questions', [ExamController::class, 'addQuestion'])->name('exams.questions.add');
        Route::post('/exams/{exam}/questions/bulk', [ExamController::class, 'bulkAddQuestions'])->name('exams.questions.bulk');
        Route::delete('/exams/{exam}/questions/{question}', [ExamController::class, 'removeQuestion'])->name('exams.questions.remove');

        // AJAX Routes for dynamic updates
        Route::post('/exams/{exam}/questions/reorder', [ExamController::class, 'reorderQuestions'])->name('exams.questions.reorder');
        Route::put('/exams/{exam}/questions/{question}/points', [ExamController::class, 'updatePoints'])->name('exams.questions.points');
    });

});
