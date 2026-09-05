<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;

class ExamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isTeacher() || $user->isAdmin();
    }

    public function view(User $user, Exam $exam): bool
    {
        return $user->isAdmin() || $exam->teacher_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isTeacher() || $user->isAdmin();
    }

    public function update(User $user, Exam $exam): bool
    {
        return $user->isAdmin() || $exam->teacher_id === $user->id;
    }

    public function delete(User $user, Exam $exam): bool
    {
        return $user->isAdmin() || $exam->teacher_id === $user->id;
    }
}
