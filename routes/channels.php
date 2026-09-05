<?php

use App\Models\Exam;
use App\Models\ExamSession;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('exam.{examId}', function ($user, $examId) {
    $exam = Exam::find($examId);

    if (! $exam) {
        return false;
    }

    if ($user->isAdmin() || $exam->teacher_id === $user->id) {
        return true;
    }

    return ExamSession::where('exam_id', $examId)
        ->where('student_id', $user->id)
        ->exists();
});

Broadcast::channel('student.{studentId}', function ($user, $studentId) {
    return (int) $user->id === (int) $studentId || $user->isAdmin();
});

Broadcast::channel('teacher.{teacherId}', function ($user, $teacherId) {
    return (int) $user->id === (int) $teacherId || $user->isAdmin();
});
