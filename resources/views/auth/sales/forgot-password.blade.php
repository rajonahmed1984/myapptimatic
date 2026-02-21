@extends('layouts.guest')

@section('title', 'Sales Password Reset')

@section('content')
    @php
        $emailAction = route('sales.password.email');
        $loginUrl = $loginRoute ?? route('sales.login');
        $emailError = $errors->first('email');
    @endphp

    <section class="relative -m-8 overflow-hidden rounded-2xl bg-slate-900 px-8 py-10 text-white sm:px-10">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_25%_20%,rgba(45,212,191,0.35),transparent_45%),radial-gradient(circle_at_80%_75%,rgba(59,130,246,0.26),transparent_44%)]"></div>
        <div class="relative z-10">
            <p class="text-xs font-semibold uppercase tracking-[0.32em] text-slate-300/80">Password reset</p>
            <p class="mt-2 text-sm text-slate-200/85">Enter your email and we will send a reset link.</p>
            @if (session('status'))
                <div class="mt-5 rounded-xl border border-emerald-300/40 bg-emerald-400/10 p-4 text-sm font-medium text-emerald-100">
                    {{ session('status') }}
                </div>
            @endif

            @if ($emailError)
                <div class="mt-5 rounded-xl border border-amber-300/40 bg-amber-400/10 px-4 py-3 text-sm font-medium text-amber-100">
                    {{ $emailError }}
                </div>
            @endif

            <form class="mt-8 space-y-5" method="POST" action="{{ $emailAction }}">
                @csrf
                <div>
                    <label class="sr-only">Email</label>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="Email"
                        required
                        class="mt-2 w-full rounded-xl border border-white/20 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-teal-300 focus:outline-none focus:ring-2 focus:ring-teal-200 @error('email') border-rose-300 @enderror"
                    />
                </div>
                <button
                    type="submit"
                    class="w-full rounded-xl bg-teal-500 px-4 py-3 text-sm font-semibold text-white transition hover:bg-teal-400"
                >
                    Send reset link
                </button>
            </form>

            <p class="mt-6 text-xs text-slate-200/85">
                Remember your password? <a href="{{ $loginUrl }}" class="font-semibold text-teal-300 hover:text-teal-200">Sign in</a>.
            </p>
        </div>
    </section>
@endsection
