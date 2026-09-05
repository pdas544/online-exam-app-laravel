<?php

namespace App\Policies;

use App\Models\ExamSession;
use App\Models\User;

class ExamSessionPolicy
{
    public function view(User $user, ExamSession $session): bool
    {
        return $user->isAdmin() || $session->student_id === $user->id;
    }

    public function forceEnd(User $user, ExamSession $session): bool
    {
        return $user->isAdmin() || $session->teacher_id === $user->id;
    }
}
