@extends('layouts.admin')

@section('title', 'Plans')
@section('page-title', 'Plans')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-slate-900">Plans</h1>
        <a href="{{ route('admin.plans.create') }}" class="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white">New Plan</a>
    </div>

    <div class="card overflow-hidden">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                <tr>
                    <th class="px-4 py-3">Plan</th>
                    <th class="px-4 py-3">Product</th>
                    <th class="px-4 py-3">Price</th>
                    <th class="px-4 py-3">Interval</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($plans as $plan)
                    <tr class="border-b border-slate-100">
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $plan->name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $plan->product->name }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $defaultCurrency }} {{ $plan->price }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ ucfirst($plan->interval) }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $plan->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.plans.edit', $plan) }}" class="text-teal-600 hover:text-teal-500">Edit</a>
                                <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" onsubmit="return confirm('Delete this plan? Subscriptions will also be removed.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 hover:text-rose-500">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-slate-500">No plans yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
