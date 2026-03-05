@extends('layouts.support')

@section('title', config('app.name', 'MyApptimatic'))
@section('page-title', 'Overview')

@push('styles')
    @viteReactRefresh
    @vite(['resources/js/app.jsx'])
    @inertiaHead
@endpush

@section('content')
    @inertia
@endsection
