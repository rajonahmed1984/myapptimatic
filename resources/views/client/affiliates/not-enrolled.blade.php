@extends('layouts.client')

@section('title', 'Affiliate Program')
@section('page-title', 'Affiliate Program')

@section('content')
    <div class="card p-8 text-center">
        <div class="mx-auto max-w-2xl">
            <div class="section-label">Affiliate Program</div>
            <h1 class="mt-3 text-3xl font-bold text-slate-900">Earn money by referring customers</h1>
            <p class="mt-4 text-lg text-slate-600">Join our affiliate program and earn commissions for every customer you refer.</p>

            <div class="mt-8 grid gap-6 md:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-6">
                    <div class="text-4xl font-bold text-teal-600">10%</div>
                    <div class="mt-2 text-sm text-slate-600">Commission Rate</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-6">
                    <div class="text-4xl font-bold text-teal-600">30d</div>
                    <div class="mt-2 text-sm text-slate-600">Cookie Duration</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-6">
                    <div class="text-4xl font-bold text-teal-600">$50</div>
                    <div class="mt-2 text-sm text-slate-600">Min Payout</div>
                </div>
            </div>

            <a href="{{ route('client.affiliates.apply') }}" class="mt-8 inline-block rounded-full bg-teal-500 px-8 py-3 text-sm font-semibold text-white transition hover:bg-teal-400">
                Apply Now
            </a>

            <div class="mt-12 text-left">
                <h2 class="text-xl font-semibold text-slate-900">How it works</h2>
                <div class="mt-6 space-y-4">
                    <div class="flex gap-4">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-teal-100 text-sm font-bold text-teal-600">1</div>
                        <div>
                            <div class="font-semibold text-slate-900">Apply to the program</div>
                            <div class="text-sm text-slate-600">Submit your application and wait for approval.</div>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-teal-100 text-sm font-bold text-teal-600">2</div>
                        <div>
                            <div class="font-semibold text-slate-900">Share your link</div>
                            <div class="text-sm text-slate-600">Get your unique referral link and share it.</div>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-teal-100 text-sm font-bold text-teal-600">3</div>
                        <div>
                            <div class="font-semibold text-slate-900">Earn commissions</div>
                            <div class="text-sm text-slate-600">Earn money when referred customers make purchases.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
