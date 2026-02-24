@extends('layouts.client')

@section('title', config('app.name', 'MyApptimatic'))
@section('page-title', 'Overview')

@push('styles')
    @vite(['resources/js/react/app.jsx'])
    @inertiaHead
@endpush

@section('content')
    @inertia
@endsection
