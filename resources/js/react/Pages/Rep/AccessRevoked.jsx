import React from 'react';
import { Head } from '@inertiajs/react';

export default function AccessRevoked({
    pageTitle = 'Access revoked',
    message = 'Your sales representative access is currently inactive.',
    routes = {},
}) {
    return (
        <>
            <Head title={pageTitle} />
            <div className="mx-auto mt-16 max-w-3xl rounded-3xl border border-rose-200 bg-rose-50 px-8 py-10 text-center shadow-sm">
                <div className="text-xs uppercase tracking-[0.35em] text-rose-500">Sales representative</div>
                <div className="mt-2 text-2xl font-semibold text-slate-900">{pageTitle}</div>
                <p className="mt-3 text-sm text-rose-700">{message}</p>
                <div className="mt-6 flex justify-center gap-3">
                    <a href={routes?.login} data-native="true" className="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">
                        Back to login
                    </a>
                </div>
            </div>
        </>
    );
}
