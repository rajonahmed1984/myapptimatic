@extends('layouts.guest')

@section('title', 'Admin Sign In')

@section('content')
    <div class="section-label">Admin login</div>
    <h2 class="mt-3 text-2xl font-semibold text-slate-900">Sign in to the control desk</h2>
    <p class="mt-2 text-sm text-slate-600">Manage products, plans, invoices, and customer access.</p>

    @if(session('status'))
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            {{ session('status') }}
        </div>
    @endif

    <form class="mt-8 space-y-5" method="POST" action="{{ route('admin.login.attempt') }}">
        @csrf
        <div>
            <label class="text-sm text-slate-600">Email</label>
            <input
                type="email"
                name="email"
                value="{{ old('email') }}"
                required
                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 focus:border-teal-400 focus:outline-none focus:ring-2 focus:ring-teal-200"
            />
        </div>
        <div>
            <label class="text-sm text-slate-600">Password</label>
            <input
                type="password"
                name="password"
                required
                class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 focus:border-teal-400 focus:outline-none focus:ring-2 focus:ring-teal-200"
            />
        </div>
        <div class="flex items-center justify-between text-sm text-slate-500">
            <label class="flex items-center gap-2">
                <input type="checkbox" name="remember" class="rounded border-slate-300 text-teal-500 focus:ring-teal-200" />
                Remember me
            </label>
            <a href="{{ route('password.request') }}" class="text-teal-600 hover:text-teal-500">Forgot password?</a>
        </div>
        @if(config('recaptcha.site_key'))
            <div class="flex justify-center">
                <div class="g-recaptcha" data-sitekey="{{ config('recaptcha.site_key') }}" data-action="ADMIN_LOGIN"></div>
            </div>
        @endif
        <button
            type="submit"
            class="w-full rounded-xl bg-teal-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-teal-500"
        >
            Sign in
        </button>
    </form>

    <p class="mt-6 text-xs text-slate-500">
        Client account? Use the <a href="{{ route('login') }}" class="font-semibold text-teal-600 hover:text-teal-500">client login</a>.
    </p>

    @if(config('recaptcha.site_key'))
        <script src="https://www.google.com/recaptcha/enterprise.js" async defer></script>
    @endif
@endsection
