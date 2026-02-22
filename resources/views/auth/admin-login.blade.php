@extends('layouts.guest')

@section('title', 'Admin Sign In')

@section('content')
    <section class="relative -m-8 overflow-hidden rounded-2xl bg-slate-900 px-8 py-10 text-white sm:px-10">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_25%_20%,rgba(45,212,191,0.35),transparent_45%),radial-gradient(circle_at_80%_75%,rgba(59,130,246,0.26),transparent_44%)]"></div>
        <div class="relative z-10">
            @include('auth.partials.card-alerts')
            <p class="text-xs font-semibold uppercase tracking-[0.36em] text-teal-200/90">Welcome Back</p>
            <form class="mt-8 space-y-5" method="POST" action="{{ route('admin.login.attempt', [], false) }}">
                @csrf
                <div>
                    <label class="sr-only">Email</label>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="Email"
                        required
                        class="mt-2 w-full rounded-xl border border-white/20 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-teal-300 focus:outline-none focus:ring-2 focus:ring-teal-200"
                    />
                </div>
                <div>
                    <label class="sr-only">Password</label>
                    <input
                        type="password"
                        name="password"
                        placeholder="Password"
                        required
                        class="mt-2 w-full rounded-xl border border-white/20 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-teal-300 focus:outline-none focus:ring-2 focus:ring-teal-200"
                    />
                </div>
                <div class="flex items-center justify-between text-sm text-slate-200/85">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" name="remember" class="rounded border-white/30 text-teal-500 focus:ring-teal-200" />
                        Remember me
                    </label>
                    <a href="{{ route('admin.password.request') }}" class="text-teal-300 hover:text-teal-200">Forgot password?</a>
                </div>
                @if(config('recaptcha.enabled') && config('recaptcha.site_key'))
                    <div class="flex justify-center">
                        <div class="g-recaptcha" data-sitekey="{{ config('recaptcha.site_key') }}" data-action="ADMIN_LOGIN"></div>
                    </div>
                @endif
                <button
                    type="submit"
                    class="w-full rounded-xl bg-teal-500 px-4 py-3 text-sm font-semibold text-white transition hover:bg-teal-400"
                >
                    Sign in
                </button>
            </form>

            <p class="mt-6 text-xs text-slate-200/85">
                Client account? Use the <a href="{{ route('login') }}" class="font-semibold text-teal-300 hover:text-teal-200">client login</a>.
            </p>
        </div>
    </section>

    @if(config('recaptcha.enabled') && config('recaptcha.site_key'))
        <script src="https://www.google.com/recaptcha/enterprise.js" async defer></script>
    @endif
@endsection
