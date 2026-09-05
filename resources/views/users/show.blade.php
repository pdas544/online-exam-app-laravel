@extends('layouts.app')

@section('title', 'User Details')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3">User Details</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Users</a></li>
                        <li class="breadcrumb-item active">{{ $user->name }}</li>
                    </ol>
                </nav>
            </div>

            <div class="d-flex justify-content-end align-items-center m-2">
                <a href="{{ route('users.edit', $user) }}" class="btn btn-primary me-2">Edit</a>
                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Back to Users</a>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ $user->name }}</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Name</dt>
                            <dd class="col-sm-8">{{ $user->name }}</dd>

                            <dt class="col-sm-4">Email</dt>
                            <dd class="col-sm-8">{{ $user->email }}</dd>

                            <dt class="col-sm-4">Role</dt>
                            <dd class="col-sm-8">
                                <span class="badge {{ $user->role == 'admin' ? 'bg-danger' : ($user->role == 'teacher' ? 'bg-primary' : 'bg-success') }}">
                                    {{ ucfirst($user->role) }}
                                </span>
                            </dd>

                            @if($user->teacher)
                                <dt class="col-sm-4">Department</dt>
                                <dd class="col-sm-8">{{ $user->teacher->department }}</dd>

                                <dt class="col-sm-4">Designation</dt>
                                <dd class="col-sm-8">{{ $user->teacher->designation }}</dd>
                            @endif

                            <dt class="col-sm-4">Created</dt>
                            <dd class="col-sm-8">{{ $user->created_at->format('M d, Y h:i A') }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
