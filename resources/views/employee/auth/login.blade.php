@extends('layouts.admin')

@section('title', 'Employee Login')
@section('page-title', 'Employee Portal')

@section('content')
    <div class="max-w-lg mx-auto card p-6">
        <div class="text-center mb-4">
            <div class="section-label">Employee Portal</div>
            <div class="text-2xl font-semibold text-slate-900">Sign in</div>
            <div class="text-sm text-slate-500">Use the credentials linked to your employee profile.</div>
        </div>

        <form method="POST" action="{{ route('employee.login.attempt') }}" class="space-y-4">
            @csrf
            <div>
                <label class="text-xs text-slate-500">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-slate-500">Password</label>
                <input type="password" name="password" required
                       class="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm">
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-emerald-600">
                <span class="text-xs text-slate-600">Remember me</span>
            </div>

            @if($errors->any())
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="flex items-center justify-between">
                <button type="submit" class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                    Sign in
                </button>
                <a href="{{ route('login') }}" class="text-xs text-slate-500 hover:text-slate-700">Back to main login</a>
            </div>
        </form>
    </div>
@endsection
