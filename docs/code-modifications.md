# Code modifications (targeted snippets)

> Note: Insert each snippet at the indicated line numbers. Only the specific parts are shown.

## 1) Secure and de-duplicate resource routes
File: routes/web.php

```php
// Place around line 37 inside the existing auth group in routes/web.php
Route::middleware(['auth'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Admin routes...
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    });

    Route::get('/teacher/dashboard', [TeacherDashboardController::class, 'index'])->name('teacher.dashboard');
    Route::get('/student/dashboard', [StudentDashboardController::class, 'index'])->name('student.dashboard');

    // Move these resource routes here (currently around lines 52–55 outside auth)
    Route::resource('users', UserController::class);
    Route::resource('subjects', SubjectController::class);
    Route::resource('exams', ExamController::class);
    Route::resource('questions', QuestionController::class);
    Route::post('/questions/{question}/duplicate', [QuestionController::class, 'duplicate'])->name('questions.duplicate');
    Route::get('questions/import', [QuestionController::class, 'import'])->name('questions.import');
});

// Remove the duplicate Route::resource('exams', ...) around line 63 in routes/web.php
```

## 2) Fix academic year/semester filtering
File: app/Http/Controllers/ExamController.php

```php
// Replace the block around lines 61–67 in app/Http/Controllers/ExamController.php
if ($request->filled('academic_year')) {
    $query->where('academic_year', $request->academic_year);
}

if ($request->filled('semester')) {
    $query->where('semester', $request->semester);
}
```

## 3) Add auth + role middleware in ExamController
File: app/Http/Controllers/ExamController.php

```php
// Replace constructor around lines 14–26 in app/Http/Controllers/ExamController.php
public function __construct()
{
    $this->middleware('auth');
    $this->middleware(function ($request, $next) {
        $user = Auth::user();
        if (!$user->isTeacher() && !$user->isAdmin()) {
            abort(403, 'Unauthorized access. Teacher or Admin privileges required.');
        }
        return $next($request);
    });
}
```

## 4) Add auth + role middleware in StudentDashboardController
File: app/Http/Controllers/Dashboard/StudentDashboardController.php

```php
// Replace constructor around lines 13–23 in app/Http/Controllers/Dashboard/StudentDashboardController.php
public function __construct()
{
    $this->middleware('auth');
    $this->middleware(function ($request, $next) {
        if (!Auth::user()->isStudent()) {
            abort(403, 'Unauthorized access. Student privileges required.');
        }
        return $next($request);
    });
}
```

## 5) Restore auth middleware in ExamSessionController
File: app/Http/Controllers/ExamSessionController.php

```php
// Replace constructor around lines 17–22 in app/Http/Controllers/ExamSessionController.php
public function __construct()
{
    $this->middleware('auth');
}
```

## 6) Add ViolationLog model
File: app/Models/ViolationLog.php (new file)

```php
// New file: app/Models/ViolationLog.php (create this file)
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ViolationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_session_id',
        'student_id',
        'exam_id',
        'violation_type',
        'description',
        'metadata',
        'severity',
        'auto_warned',
        'auto_terminated',
        'detected_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'auto_warned' => 'boolean',
        'auto_terminated' => 'boolean',
        'detected_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(ExamSession::class, 'exam_session_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }
}
```

## 7) Fix missing import in AnswerSaved event
File: app/Events/AnswerSaved.php

```php
// Add this import around line 5 in app/Events/AnswerSaved.php
use Illuminate\Broadcasting\Channel;
```

## 8) Initialize config in TeacherMonitor
File: resources/js/teacher-monitor.js

```javascript
// Replace constructor around lines 1–6 in resources/js/teacher-monitor.js
constructor(examId, config = {}) {
    this.examId = examId;
    this.config = config;
    this.sessions = [];
    this.init();
}
```

## 9) Reuse auto-grade and reduce repeated queries on submit
File: app/Http/Controllers/ExamSessionController.php

```php
// Replace the auto-grade block around lines 175–183 in app/Http/Controllers/ExamSessionController.php
$session->load('answers.question');
foreach ($session->answers as $answer) {
    if (!$answer->is_answered) {
        $answer->update([
            'is_correct' => false,
            'points_earned' => 0,
        ]);
        continue;
    }

    $answer->autoGrade();
}
```
