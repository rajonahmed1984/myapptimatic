@extends('layouts.guest')

@section('title', 'Support Reset Password')

@section('content')
    <section class="relative -m-8 overflow-hidden rounded-2xl bg-slate-900 px-8 py-10 text-white sm:px-10">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_25%_20%,rgba(45,212,191,0.35),transparent_45%),radial-gradient(circle_at_80%_75%,rgba(59,130,246,0.26),transparent_44%)]"></div>
        <div class="relative z-10">
            <form class="mt-8 space-y-5" method="POST" action="{{ route('support.password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}" />
                <div>
                    <label class="sr-only">Email</label>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email', request('email')) }}"
                        placeholder="Email"
                        required
                        class="mt-2 w-full rounded-xl border border-white/20 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-teal-300 focus:outline-none focus:ring-2 focus:ring-teal-200"
                    />
                </div>
                <div>
                    <label class="sr-only">New password</label>
                    <input
                        type="password"
                        name="password"
                        placeholder="New password"
                        required
                        class="mt-2 w-full rounded-xl border border-white/20 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-teal-300 focus:outline-none focus:ring-2 focus:ring-teal-200"
                    />
                </div>
                <div>
                    <label class="sr-only">Confirm password</label>
                    <input
                        type="password"
                        name="password_confirmation"
                        placeholder="Confirm password"
                        required
                        class="mt-2 w-full rounded-xl border border-white/20 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-teal-300 focus:outline-none focus:ring-2 focus:ring-teal-200"
                    />
                </div>
                <button
                    type="submit"
                    class="w-full rounded-xl bg-teal-500 px-4 py-3 text-sm font-semibold text-white transition hover:bg-teal-400"
                >
                    Reset password
                </button>
            </form>

            <p class="mt-6 text-xs text-slate-200/85">
                Back to <a href="{{ $loginRoute ?? route('support.login') }}" class="font-semibold text-teal-300 hover:text-teal-200">sign in</a>.
            </p>
        </div>
    </section>
@endsection
