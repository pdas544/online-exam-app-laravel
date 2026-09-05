@extends('layouts.app')

@section('title', 'Import Questions')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3">Import Questions</h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('questions.index') }}">Questions</a></li>
                        <li class="breadcrumb-item active">Import</li>
                    </ol>
                </nav>
            </div>

            <a href="{{ route('questions.index') }}" class="btn btn-outline-secondary m-2">Back to Questions</a>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Bulk Import</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info" role="alert">
                            CSV bulk import is not implemented yet. Please create questions manually for now.
                        </div>
                        <a href="{{ route('questions.create') }}" class="btn btn-primary">Create Question Manually</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
