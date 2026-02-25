@extends('layouts.public')

@section('title', config('app.name', 'MyApptimatic'))

@push('styles')
    @viteReactRefresh
    @vite(['resources/js/react/app.jsx'])
    @inertiaHead
@endpush

@section('content')
    @inertia
@endsection
