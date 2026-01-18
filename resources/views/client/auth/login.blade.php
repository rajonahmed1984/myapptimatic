@extends('layouts.guest')

@section('title', 'Client Login')

@section('content')
    <div class="section-label">Client portal</div>
    <h2 class="mt-3 text-2xl font-semibold text-slate-900">Sign in to your account</h2>
    <p class="mt-2 text-sm text-slate-600">Access your invoices, orders, services, and more.</p>

    <form class="mt-8 space-y-5" method="POST" action="{{ route('client.login.attempt') }}">
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
            <a href="{{ route('password.request') }}" class="text-emerald-600 hover:text-emerald-500">Forgot password?</a>
        </div>

        @if(config('recaptcha.enabled') && config('recaptcha.site_key'))
            <div class="flex justify-center">
                <div class="g-recaptcha" data-sitekey="{{ config('recaptcha.site_key') }}" data-action="CLIENT_LOGIN"></div>
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <button type="submit" class="w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-400">
            Sign in
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-600">
        Don't have an account? <a href="{{ route('register') }}" class="text-emerald-600 hover:text-emerald-500">Create one here</a>
    </p>
@endsection
