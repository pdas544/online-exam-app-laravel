<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct()
    {
        // Apply auth middleware to all methods in this controller
          $this->middleware('auth');
    }

    public function index()
    {
        // Manual role check - make sure user is admin
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        $stats = [
            'total_users' => User::count(),
            'total_students' => User::where('role', 'student')->count(),
            'total_teachers' => User::where('role', 'teacher')->count(),
            'total_admins' => User::where('role', 'admin')->count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}
