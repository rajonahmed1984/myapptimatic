@extends('layouts.admin')

@section('title', 'Edit Accounting Entry')
@section('page-title', 'Edit Accounting Entry')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-900">Edit Accounting Entry</h1>
        <p class="mt-1 text-sm text-slate-500">Update this ledger record.</p>
    </div>

    <form method="POST" action="{{ route('admin.accounting.update', $entry) }}" class="card p-6">
        @csrf
        @method('PUT')
        @include('admin.accounting._form', ['entry' => $entry])
        <div class="mt-6 flex justify-end">
            <button type="submit" class="rounded-full bg-slate-900 px-5 py-2 text-sm font-semibold text-white">Update entry</button>
        </div>
    </form>
@endsection
