@extends('layouts.support')

@section('title', 'Support Dashboard')
@section('page-title', 'Support Dashboard')

@section('content')
    <div class="card p-6">
        <div class="section-label">Overview</div>
        <div class="mt-2 text-lg font-semibold text-slate-900">Support workspace</div>
        <p class="mt-2 text-sm text-slate-600">Review and reply to client support tickets.</p>
        <div class="mt-5">
            <a href="{{ route('support.support-tickets.index') }}" class="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-400">
                View tickets
            </a>
        </div>
    </div>
@endsection
