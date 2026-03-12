@extends('layouts.app')

@section('title', $title ?? ucfirst($role) . ' Dashboard')

@section('content')
    <div class="container-fluid">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0">{{ $title ?? ucfirst($role) . ' Dashboard' }}</h1>
                        <p class="text-muted mb-0">
                            <i class="bi bi-calendar me-2"></i>{{ now()->format('l, F j, Y') }}
                        </p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-dark p-2">
                        <i class="bi bi-person-circle me-1"></i>
                        Logged in as <strong>{{ ucfirst($role) }}</strong>
                    </span>
                        <form method="POST" action="{{ route('logout') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            @if(isset($stats) && is_array($stats) && count($stats) > 0)
                @foreach($stats as $stat)
                    @if(is_array($stat))
                        @include('dashboard.components.stats-card', $stat)
                    @endif
                @endforeach
            @else
                <!-- Fallback when no stats are available -->
                <div class="col-12">
                    <div class="alert alert-info">
                        No statistics available at the moment.
                    </div>
                </div>
            @endif
        </div>

        <!-- Quick Actions -->
        @if(isset($quickActions) && is_array($quickActions) && count($quickActions) > 0)
            @include('dashboard.components.quick-actions', ['actions' => $quickActions])
        @endif

        <!-- Main Content Row -->
        <div class="row">
            <!-- Left Column (8 cols) - Role-specific content -->
            <div class="col-md-8">
                @yield('dashboard-main')
            </div>

            <!-- Right Column (4 cols) - Recent Activity -->
            <!-- Right Column (4 cols) - Recent Activity -->
            <div class="col-md-4">
                @if(isset($recentActivity) && is_array($recentActivity))
                    @include('dashboard.components.recent-activity', [
                        'items' => $recentActivity,
                        'title' => $activityTitle ?? 'Recent Activity'
                    ])
                @else
                    <div class="card">
                        <div class="card-body text-center text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            <span>No recent activity</span>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
