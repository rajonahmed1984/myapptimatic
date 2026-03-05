import React from 'react';
import { Head } from '@inertiajs/react';

export default function NotEnrolled({ routes = {} }) {
    return (
        <>
            <Head title="Affiliate Program" />

            <div className="card p-8 text-center">
                <div className="mx-auto max-w-2xl">
                    <div className="section-label">Affiliate Program</div>
                    <h1 className="mt-3 text-3xl font-bold text-slate-900">Earn money by referring customers</h1>
                    <p className="mt-4 text-lg text-slate-600">Join our affiliate program and earn commissions for every customer you refer.</p>

                    <div className="mt-8 grid gap-6 md:grid-cols-3">
                        <div className="rounded-2xl border border-slate-200 bg-white p-6">
                            <div className="text-4xl font-bold text-teal-600">10%</div>
                            <div className="mt-2 text-sm text-slate-600">Commission Rate</div>
                        </div>
                        <div className="rounded-2xl border border-slate-200 bg-white p-6">
                            <div className="text-4xl font-bold text-teal-600">30d</div>
                            <div className="mt-2 text-sm text-slate-600">Cookie Duration</div>
                        </div>
                        <div className="rounded-2xl border border-slate-200 bg-white p-6">
                            <div className="text-4xl font-bold text-teal-600">$50</div>
                            <div className="mt-2 text-sm text-slate-600">Min Payout</div>
                        </div>
                    </div>

                    <a href={routes.apply} data-native="true" className="mt-8 inline-block rounded-full bg-teal-500 px-8 py-3 text-sm font-semibold text-white transition hover:bg-teal-400">
                        Apply Now
                    </a>
                </div>
            </div>
        </>
    );
}
