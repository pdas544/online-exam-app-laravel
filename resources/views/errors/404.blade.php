@extends('layouts.app')

@section('title', 'Page not found')

@section('content')
<div class="container py-5 text-center">
    <h1 class="display-4">404</h1>
    <p class="lead">Page not found. The link may be broken or the page removed.</p>
    <a href="{{ route('home') }}" class="btn btn-primary">Go home</a>
</div>
@endsection
