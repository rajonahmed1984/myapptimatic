@extends('layouts.guest')

@section('title', 'Support Password Reset')

@section('content')
    <div class="section-label">Support portal</div>
    <h2 class="mt-3 text-2xl font-semibold text-slate-900">Forgot your password?</h2>
    <p class="mt-2 text-sm text-slate-600">Enter your email and we will send a reset link.</p>
    @php
        $emailAction = route('support.password.email');
        $loginUrl = $loginRoute ?? route('support.login');
        $emailError = $errors->first('email');
    @endphp

    @if (session('status'))
        <div class="mt-5 rounded-lg bg-emerald-50 border border-emerald-200 p-4">
            <p class="text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </p>
        </div>
    @endif

    @if ($emailError)
        <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
            {{ $emailError }}
        </div>
    @endif

    <form class="mt-8 space-y-5" method="POST" action="{{ $emailAction }}">
        @csrf
        <div>
            <label class="text-sm text-slate-600">Email</label>
            <input
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 focus:border-teal-400 focus:outline-none focus:ring-2 focus:ring-teal-200 @error('email') border-red-400 @enderror"
            />
        </div>
        <button
            type="submit"
            class="w-full rounded-xl bg-teal-500 px-4 py-3 text-sm font-semibold text-white transition hover:bg-teal-400"
        >
            Send reset link
        </button>
    </form>

    <p class="mt-6 text-xs text-slate-500">
        Remember your password? <a href="{{ $loginUrl }}" class="font-semibold text-teal-600 hover:text-teal-500">Sign in</a>.
    </p>
@endsection
