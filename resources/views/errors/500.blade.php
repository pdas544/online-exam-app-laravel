@extends('layouts.app')

@section('title', 'Something went wrong')

@section('content')
<div class="container py-5 text-center">
    <h1 class="display-4">500</h1>
    <p class="lead">Something went wrong. Please try again later.</p>
    <a href="{{ route('home') }}" class="btn btn-primary">Go home</a>
</div>
@endsection
