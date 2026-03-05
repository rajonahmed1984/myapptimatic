@extends('layouts.admin')

@section('title', config('app.name', 'MyApptimatic'))
@section('page-title', 'Overview')

@php
    $hasViteAssets = file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json'));
@endphp

@push('styles')
    @if ($hasViteAssets)
        @viteReactRefresh
        @vite(['resources/js/app.jsx'])
    @endif
    @inertiaHead
@endpush

@section('content')
    @if ($hasViteAssets)
        @inertia
    @else
        <div style="padding:16px;border:1px solid #fca5a5;background:#fef2f2;color:#991b1b;border-radius:8px;">
            Frontend assets are missing. Run <code>npm run dev</code> (development) or <code>npm run build</code> (production) and reload.
        </div>
    @endif
@endsection
