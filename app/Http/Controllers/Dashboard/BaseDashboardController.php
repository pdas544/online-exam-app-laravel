<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

abstract class BaseDashboardController extends Controller
{
    protected $user;
    protected $role;

    public function __construct()
    {

            $this->user = Auth::user();
            $this->role = $this->user->role;

            // Verify user has access to this dashboard
            if (!$this->checkAccess()) {
                abort(403, 'Unauthorized dashboard access.');
            }



    }

    abstract protected function checkAccess(): bool;
    abstract protected function getStats(): array;
    abstract protected function getQuickActions(): array;
    abstract protected function getRecentActivity(): array;
}
