@extends('layouts.admin')

@section('title', 'Admin Dashboard')
@section('page-title', 'Admin Overview')

@section('content')
    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3 stagger">
        <div class="card p-6">
            <div class="section-label">Customers</div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $customerCount }}</div>
        </div>
        <div class="card p-6">
            <div class="section-label">Products</div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $productCount }}</div>
        </div>
        <div class="card p-6">
            <div class="section-label">Subscriptions</div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $subscriptionCount }}</div>
        </div>
        <div class="card p-6">
            <div class="section-label">Licenses</div>
            <div class="mt-3 text-3xl font-semibold text-slate-900">{{ $licenseCount }}</div>
        </div>
        <div class="card p-6">
            <div class="section-label">Overdue Invoices</div>
            <div class="mt-3 text-3xl font-semibold text-amber-600">{{ $overdueCount }}</div>
        </div>
    </div>
@endsection
