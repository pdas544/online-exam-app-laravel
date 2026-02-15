<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Online Exam System')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
<body>
<nav class="navbar navbar-expand-md bg-primary" data-bs-theme="dark">
    <div class="container-fluid ">
        <a class="navbar-brand" href="{{ route('home') }}">Exam System</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="#">Home</a>
                </li>
                @auth
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            {{ auth()->user()->name }} ({{ ucfirst(auth()->user()->role) }})
                        </a>
                        <ul class="dropdown-menu">
                            @if(auth()->user()->isAdmin())
                                <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}">Admin Dashboard</a></li>
                                <li><a class="dropdown-item" href="{{route('users.index')}}">Manage Users</a></li>
                                <li><a class="dropdown-item" href={{route('subjects.index')}}>Manage Subjects</a></li>
                                <li><a class="dropdown-item" href="{{route('exams.index')}}">Manage Exams</a></li>
                                <li><a class="dropdown-item" href="{{route('questions.index')}}">Manage Questions</a></li>
                                <li><hr class="dropdown-divider"></li>
                            @elseif(auth()->user()->isTeacher())
                                <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}">Teacher Dashboard</a></li>
                                <li><a class="dropdown-item" href="{{ route('exams.index') }}">My Exams</a></li>
                                <li><a class="dropdown-item" href="{{ route('questions.index') }}">My Questions</a></li>
                                <li><hr class="dropdown-divider"></li>
                            @else
                                <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}">Student Dashboard</a></li>
                                <li><a class="dropdown-item" href="{{ route('exams.available') }}">Available Exams</a></li>
                                <li><a class="dropdown-item" href="{{ route('results.index') }}">My Results</a></li>
                                <li><hr class="dropdown-divider"></li>
                            @endif
                            <li>
                                <form method="POST" action="{{ route('logout') }}" id="logout-form">
                                    @csrf
                                    <a class="dropdown-item" href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                        Logout
                                    </a>
                                </form>
                            </li>
                        </ul>
                    </li>
                @else
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('login') }}">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('register') }}">Register</a>
                    </li>
                @endauth
            </ul>
        </div>
    </div>
</nav>

<main class="p-2">

        @yield('header-buttons-right')

    @yield('content')
</main>

@yield('scripts')
</body>
</html>

