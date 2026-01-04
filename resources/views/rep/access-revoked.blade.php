@extends('layouts.public')

@section('title', 'Access revoked')

@section('content')
    <div class="mx-auto max-w-3xl rounded-3xl border border-rose-200 bg-rose-50 px-8 py-10 text-center shadow-sm mt-16">
        <div class="text-xs uppercase tracking-[0.35em] text-rose-500">Sales representative</div>
        <div class="mt-2 text-2xl font-semibold text-slate-900">Access revoked</div>
        <p class="mt-3 text-sm text-rose-700">
            Your sales representative access is currently inactive. If you believe this is an error, please contact an administrator.
        </p>
        <div class="mt-6 flex justify-center gap-3">
            <a href="{{ route('login') }}" class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back to login</a>
        </div>
    </div>
@endsection
