<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeacherMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        if (!Auth::user()->isTeacher() && !Auth::user()->isAdmin()) {
            abort(403, 'Unauthorized access. Teacher privileges required.');
        }

        return $next($request);
    }
}
