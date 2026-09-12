@extends('layouts.app')

@section('title', 'Session expired')

@section('content')
<div class="container py-5 text-center">
    <h1 class="display-4">419</h1>
    <p class="lead">Session expired. Please refresh the page and try again.</p>
    <a href="{{ route('home') }}" class="btn btn-primary">Go home</a>
</div>
@endsection
