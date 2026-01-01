@extends('layouts.admin')

@section('title', 'Affiliates')
@section('page-title', 'Affiliates')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="section-label">Affiliate Management</div>
            <h1 class="mt-2 text-2xl font-semibold text-slate-900">Manage affiliates</h1>
            <p class="mt-2 text-sm text-slate-600">Track and manage your affiliate partners.</p>
        </div>
        <a href="{{ route('admin.affiliates.create') }}" class="rounded-full bg-teal-500 px-6 py-2 text-sm font-semibold text-white transition hover:bg-teal-400">
            Add affiliate
        </a>
    </div>

    <div class="card p-6">
        <form method="GET" class="mb-6 flex flex-wrap gap-4">
            <input 
                type="text" 
                name="search" 
                value="{{ request('search') }}" 
                placeholder="Search by name, email, or code..." 
                class="flex-1 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm"
            />
            <select name="status" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm">
                <option value="">All statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                <option value="suspended" @selected(request('status') === 'suspended')>Suspended</option>
            </select>
            <button type="submit" class="rounded-full bg-slate-900 px-6 py-2 text-sm font-semibold text-white">
                Filter
            </button>
            @if(request()->hasAny(['search', 'status']))
                <a href="{{ route('admin.affiliates.index') }}" class="rounded-full border border-slate-200 px-6 py-2 text-sm font-semibold text-slate-600">
                    Clear
                </a>
            @endif
        </form>

        @if($affiliates->isEmpty())
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-8 text-center text-sm text-slate-600">
                No affiliates found.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="border-b border-slate-200 text-left text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="pb-3 font-semibold">Affiliate</th>
                            <th class="pb-3 font-semibold">Code</th>
                            <th class="pb-3 font-semibold">Status</th>
                            <th class="pb-3 font-semibold">Commission</th>
                            <th class="pb-3 font-semibold">Balance</th>
                            <th class="pb-3 font-semibold">Referrals</th>
                            <th class="pb-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        @foreach($affiliates as $affiliate)
                            <tr class="hover:bg-slate-50">
                                <td class="py-4">
                                    <div class="font-semibold text-slate-900">{{ $affiliate->customer->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $affiliate->customer->email }}</div>
                                </td>
                                <td class="py-4">
                                    <code class="rounded bg-slate-100 px-2 py-1 text-xs font-mono text-slate-700">{{ $affiliate->affiliate_code }}</code>
                                </td>
                                <td class="py-4">
                                    <x-status-badge :status="$affiliate->status" />
                                </td>
                                <td class="py-4">
                                    @if($affiliate->commission_type === 'percentage')
                                        {{ $affiliate->commission_rate }}%
                                    @else
                                        ${{ number_format($affiliate->fixed_commission_amount, 2) }}
                                    @endif
                                </td>
                                <td class="py-4 font-semibold">
                                    ${{ number_format($affiliate->balance, 2) }}
                                </td>
                                <td class="py-4">
                                    {{ $affiliate->total_referrals }} / {{ $affiliate->total_conversions }}
                                </td>
                                <td class="py-4">
                                    <a href="{{ route('admin.affiliates.show', $affiliate) }}" class="text-teal-600 hover:text-teal-500">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $affiliates->links() }}
            </div>
        @endif
    </div>
@endsection
