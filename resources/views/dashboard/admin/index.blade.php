@extends('dashboard.layouts.base', [
    'role' => 'admin',
    'title' => 'Administrator Dashboard',
    'stats' => $stats,
    'quickActions' => $quickActions,
    'recentActivity' => $recentActivity
])

@section('dashboard-main')
    <!-- User Growth Chart -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="card-title mb-0">
                <i class="bi bi-graph-up me-2"></i>User Growth
            </h5>
        </div>
        <div class="card-body">
            <canvas id="userGrowthChart" height="100"></canvas>
        </div>
    </div>

    <!-- Recent Users Table -->
    <div class="card">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">
                <i class="bi bi-people me-2"></i>Recent Users
            </h5>
            <a href="{{ route('users.index') }}" class="btn btn-sm btn-primary">
                View All <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Joined</th>
                </tr>
                </thead>
                <tbody>
                @foreach($stats['recent_users'] as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            <span class="badge bg-{{ $user->role === 'admin' ? 'danger' : ($user->role === 'teacher' ? 'info' : 'success') }}">
                                {{ ucfirst($user->role) }}
                            </span>
                        </td>
                        <td>{{ $user->created_at->diffForHumans() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
