@extends('layouts.app')

@section('title', 'Exam Management')
@section('page-title', 'Exam Management')


@section('content')
    <div class="d-flex justify-content-between align-items-center m-3">
        <div>
            <h1 class="h3">Exam Management</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    @if (auth()->user()->isAdmin())
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin Dashboard</a></li>
                    @elseif (auth()->user()->isTeacher())
                    <li class="breadcrumb-item"><a href="{{ route('teacher.dashboard') }}">Teacher Dashboard</a></li>
                    @else
                    <li class="breadcrumb-item"><a href="{{ route('student.dashboard') }}">Student Dashboard</a></li>
                    
                    @endif
                    <li class="breadcrumb-item active">Exams</li>
                </ol>
            </nav>
        </div>


        <div class="">
            <a href="{{ route('exams.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Add Exam
            </a>
        </div>



    </div>
    <div class="card">
        <div class="card-body">
            <!-- Filters -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <form method="GET" action="{{ route('exams.index') }}" class="row g-3">
                        <div class="col-md-2">
                            <select name="subject_id" class="form-select" onchange="this.form.submit()">
                                <option value="">All Subjects</option>
                                @foreach($subjects as $subject)
                                    <option value="{{ $subject->id }}"
                                        {{ request('subject_id') == $subject->id ? 'selected' : '' }}>
                                        {{ $subject->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="published" {{ request('status') == 'published' ? 'selected' : '' }}>Published</option>
                                <option value="archived" {{ request('status') == 'archived' ? 'selected' : '' }}>Archived</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control"
                                       placeholder="Search exams..." value="{{ request('search') }}">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <div class="input-group">
                                <input type="text" name="year" class="form-control"
                                       placeholder="Search by year" value="{{ request('year') }}">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="input-group">
                                <input type="text" name="semester" class="form-control"
                                       placeholder="Search by semester" value="{{ request('semester') }}">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-1 ms-auto">
                            <a href="{{ route('exams.index') }}" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-x-circle"></i> Clear
                            </a>
                        </div>
                    </form>
                </div>

            </div>



            <!-- Exams Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                    <tr>

                        <th>Title</th>
                        <th>Subject</th>
                        <th>Semester</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($exams as $exam)
                        <tr>

                            <td>
                                <strong>{{ Str::limit($exam->title, 40) }}</strong>
                                <br>
                                <small class="text-muted">Created: {{ $exam->created_at->format('M d, Y') }}</small>
                            </td>
                            <td>
                            <span class="badge bg-light text-dark">
                                <i class="bi bi-book me-1"></i>
                                {{ $exam->subject->name }}
                            </span>
                            </td>
                            <td>
                            <span class="badge bg-secondary">
                                <i class="bi bi-clock me-1"></i>
                                {{ $exam->semester }}
                            </span>
                            </td>


                            <td>
                                @php
                                    $statusColors = [
                                        'draft' => 'secondary',
                                        'published' => 'success',
                                        'archived' => 'danger'
                                    ];
                                @endphp
                                <span class="badge bg-{{ $statusColors[$exam->status] }}">
                                {{ ucfirst($exam->status) }}
                            </span>
                            </td>

                            <td>
                                <div class="btn-group gap-2" role="group">
                                    <a href="{{ route('exams.show', $exam) }}"
                                       class="btn btn-sm btn-info" title="View" data-bs-toggle="tooltip">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('monitor.exam', $exam) }}"
                                       class="btn btn-sm btn-success" title="Monitor" data-bs-toggle="tooltip">
                                        <i class="bi bi-play-circle"></i>
                                    </a>
                                    <a href="{{ route('exams.edit', $exam) }}"
                                       class="btn btn-sm btn-warning" title="Edit" data-bs-toggle="tooltip">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="{{ route('exams.questions', $exam) }}"
                                       class="btn btn-sm btn-primary" title="Manage Questions" data-bs-toggle="tooltip">
                                        <i class="bi bi-list"></i>
                                    </a>
                                    <button type="button"
                                            class="btn btn-sm btn-danger"
                                            title="Delete"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteModal{{ $exam->id }}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>

                                <!-- Delete Modal -->
                                <div class="modal fade" id="deleteModal{{ $exam->id }}" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header bg-danger text-white">
                                                <h5 class="modal-title">Confirm Delete</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <p>Are you sure you want to delete this exam?</p>
                                                <div class="alert alert-warning">
                                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                                    <strong>{{ $exam->title }}</strong>
                                                </div>
                                                <p class="text-muted small">
                                                    This will also remove all question associations.
                                                </p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <form action="{{ route('exams.destroy', $exam) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger">
                                                        <i class="bi bi-trash"></i> Delete Exam
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="bi bi-file-alt fa-4x mb-3"></i>
                                    <h5>No Exams Found</h5>
                                    <p>Get started by creating your first exam.</p>
                                    <a href="{{ route('exams.create') }}" class="btn btn-primary">
                                        <i class="bi bi-plus"></i> Create Exam
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($exams->hasPages())
                <div class="d-flex justify-content-center mt-4">
                    {{ $exams->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
