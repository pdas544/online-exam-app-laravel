@extends('layouts.app')

@section('title', 'Access denied')

@section('content')
<div class="container py-5 text-center">
    <h1 class="display-4">403</h1>
    <p class="lead">Access denied. You do not have permission to view this page.</p>
    <a href="{{ url()->previous() }}" class="btn btn-primary">Go back</a>
</div>
@endsection
