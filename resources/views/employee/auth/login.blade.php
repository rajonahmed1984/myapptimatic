@extends('layouts.guest')

@section('title', 'Employee Login')

@section('content')
    <div class="section-label">Employee portal</div>
    <h2 class="mt-3 text-2xl font-semibold text-slate-900">Sign in to your workspace</h2>
    <p class="mt-2 text-sm text-slate-600">Use the credentials linked to your employee profile.</p>

    <form class="mt-8 space-y-5" method="POST" action="{{ route('employee.login.attempt', [], false) }}">
        @csrf
        <div>
            <label class="text-sm text-slate-600">Email</label>
            <input
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200"
            />
        </div>
        <div>
            <label class="text-sm text-slate-600">Password</label>
            <input
                type="password"
                name="password"
                required
                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-200"
            />
        </div>
        <div class="flex items-center justify-between text-sm text-slate-500">
            <label class="flex items-center gap-2">
                <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-200" />
                Remember me
            </label>
            <a href="{{ route('employee.password.request') }}" class="text-emerald-600 hover:text-emerald-500">Forgot password?</a>
        </div>

        @if(config('recaptcha.enabled') && config('recaptcha.site_key'))
            <div class="flex justify-center">
                <div class="g-recaptcha" data-sitekey="{{ config('recaptcha.site_key') }}" data-action="EMPLOYEE_LOGIN"></div>
            </div>
        @endif

        <button
            type="submit"
            class="w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-500"
        >
            Sign in
        </button>
    </form>

    <p class="mt-6 text-xs text-slate-500">
        Back to <a href="{{ route('login') }}" class="font-semibold text-emerald-600 hover:text-emerald-500">main login</a>.
    </p>

    @if(config('recaptcha.enabled') && config('recaptcha.site_key'))
        <script src="https://www.google.com/recaptcha/enterprise.js" async defer></script>
    @endif
@endsection
