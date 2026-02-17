@extends('layouts.app')

@section('title', 'Subjects Management')
@section('page-title', 'View All Subjects')

@section('content')
    <div class="d-flex justify-content-between align-items-center m-3">
        <div>
            <h1 class="h3">Subject Management</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin Dashboard</a></li>
                    <li class="breadcrumb-item active">Subjects</li>
                </ol>
            </nav>
        </div>


            <div class="">
                <a href="{{ route('subjects.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Add Subject
                </a>
            </div>



    </div>
    <div class="card">
        <div class="card-body">
{{--            display flash messages--}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            <div class="row mb-4">
                <div class="col-md-6">
                    <form method="GET" action="{{ route('subjects.index') }}">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control"
                                   placeholder="Search subjects..." value="{{ request('search') }}">
                            <button class="btn btn-outline-secondary" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Subjects Table -->
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Questions</th>
                        <th>Created By</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($subjects as $subject)
                        <tr>
                            <td>{{ $subject->id }}</td>
                            <td>
                                <strong>{{ $subject->name }}</strong>
                            </td>
                            <td>
                                {{ Str::limit($subject->description, 50) }}
                            </td>
                            <td>
                            <span class="badge bg-info">
                                {{ $subject->questions_count ?? 0 }}
                            </span>
                            </td>
                            <td>
                                <small>{{ $subject->creator->name ?? 'N/A' }}</small>
                            </td>
                            <td>
                                <small>{{ $subject->created_at->format('M d, Y') }}</small>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="{{ route('subjects.show',$subject) }}"
                                       class="btn btn-sm btn-info me-1" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('subjects.edit', $subject) }}"
                                       class="btn btn-sm btn-warning me-1" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="{{ route('subjects.destroy', $subject) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete this subject?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="fas fa-book fa-2x mb-3"></i>
                                    <p>No subjects found. Create your first subject!</p>
                                    <a href="" class="btn btn-primary">
                                        Create Subject
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($subjects->hasPages())
                <div class="ms-1 d-flex justify-content-center mt-4">
                    {{ $subjects->links() }}
                </div>
            @endif

            <!-- Stats -->
            <div class="mt-4 pt-3 border-top">
                <div class="row">
                    <div class="col-md-3 mx-auto">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <h5 class="card-title">{{ $subjects->total() }}</h5>
                                <p class="card-text text-muted">Total Subjects</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
