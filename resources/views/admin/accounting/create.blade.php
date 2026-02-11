@extends('layouts.admin')

@section('title', 'New Accounting Entry')
@section('page-title', 'New Accounting Entry')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <p class="mt-1 text-sm text-slate-500">Record payments, refunds, credits, and expenses.</p>
        </div>
        <a href="{{ route('admin.accounting.index') }}" class="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600" hx-boost="false">Back to accounting</a>
    </div>

    <form method="POST" action="{{ route('admin.accounting.store') }}" class="card p-6">
        @csrf
        @include('admin.accounting._form')
        <div class="mt-6 flex justify-end">
            <button type="submit" class="rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white">Save entry</button>
        </div>
    </form>
@endsection
