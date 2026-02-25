@extends('layouts.app')

@section('title', 'Home - Online Exam System')

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Welcome to Online Exam System</span>
                            @auth
                                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Logout</button>
                                </form>
                            @endauth
                        </div>
                    </div>

                    <div class="card-body">
                        @if (session('status'))
                            <div class="alert alert-success" role="alert">
                                {{ session('status') }}
                            </div>
                        @endif

                        @auth
                            <div class="alert alert-success">
                                <h5>Welcome, {{ auth()->user()->name }}!</h5>
                                <p>You are logged in as <strong>{{ ucfirst(auth()->user()->role) }}</strong></p>

                                @if(auth()->user()->isAdmin())
                                    <div class="mt-3">
                                        <a href="{{ route('admin.dashboard') }}" class="btn btn-primary me-2">Admin
                                            Dashboard</a>
                                        <a href="{{ route('users.index') }}" class="btn btn-outline-primary">Manage
                                            Users</a>
                                        <a href="{{ route('exams.index') }}" class="btn btn-outline-primary">Manage
                                            Exams</a>
                                        <a href="{{ route('questions.index') }}" class="btn btn-outline-primary">Manage
                                            Questions</a>
                                    </div>
                                @elseif(auth()->user()->isTeacher())
                                    <div class="mt-3">
                                        <a href="{{route('teacher.dashboard')}}" class="btn btn-primary">Teacher Dashboard</a>
                                    </div>
                                @else
                                    <div class="mt-3">
                                        <a href="{{route('student.dashboard')}}" class="btn btn-primary">Student Dashboard</a>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="text-center">
                                <p>Please login or register to access the system.</p>
                                <div class="mt-3">
                                    <a href="{{ route('login') }}" class="btn btn-primary me-2">Login</a>
                                    <a href="{{ route('register') }}" class="btn btn-outline-primary">Register</a>
                                </div>
                            </div>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
