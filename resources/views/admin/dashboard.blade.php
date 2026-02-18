@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
    <div class="container-fluid">
        <!-- Header with Logout -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="h3 mb-0">Admin Dashboard</h1>
                    <form method="POST" action="{{ route('logout') }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Users</h5>
                        <h2 class="card-text">{{ $stats['total_users'] }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Students</h5>
                        <h2 class="card-text">{{ $stats['total_students'] }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Teachers</h5>
                        <h2 class="card-text">{{ $stats['total_teachers'] }}</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Admins</h5>
                        <h2 class="card-text">{{ $stats['total_admins'] }}</h2>
                    </div>
                </div>
            </div>
        </div>


        <div class="row mt-4">
            <div class="col-md-3 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Subjects</h6>
                    </div>
                    <div class="card-body text-center">
                        <h1 class="display-4">{{ $stats['total_subjects'] }}</h1>
                        <p class="text-muted">Total Subjects</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Questions</h6>
                    </div>
                    <div class="card-body text-center">
                        <h1 class="display-4">{{ $stats['total_questions'] }}</h1>
                        <p class="text-muted">Questions in Bank</p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Exams</h6>
                    </div>
                    <div class="card-body text-center">
                        <h1 class="display-4">{{ $stats['total_exams'] }}</h1>
                        <p class="text-muted">Exams Created</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">

                        <a href="{{ route('users.index') }}" class="btn btn-primary me-2">Manage Users</a>
                        <a href="{{ route('subjects.index') }}" class="btn btn-info me-2">Manage Subjects</a>
                        <a href="{{ route('questions.index') }}" class="btn btn-warning me-2">Manage Questions</a>
                        <a href="{{ route('exams.index') }}" class="btn btn-danger me-2">Manage Exams</a>
                        <a href="{{ route('users.create') }}" class="btn btn-success">Add New User</a>
                        <a href="{{ route('home') }}" class="btn btn-outline-secondary ms-2">Back to Home</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
