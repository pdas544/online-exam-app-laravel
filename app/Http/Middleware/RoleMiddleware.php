<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Check if user has the role
        if ($role === 'admin' && !$user->isAdmin()) {
            abort(403, 'Admin access required.');
        }

        if ($role === 'teacher' && !$user->isTeacher()) {
            abort(403, 'Teacher access required.');
        }

        if ($role === 'student' && !$user->isStudent()) {
            abort(403, 'Student access required.');
        }

        return $next($request);

    }
}
