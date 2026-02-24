import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({ routes = {} }) {
    return (
        <>
            <Head title="Support Dashboard" />

            <div className="card p-6">
                <div className="section-label">Overview</div>
                <div className="mt-2 text-lg font-semibold text-slate-900">Support workspace</div>
                <p className="mt-2 text-sm text-slate-600">Review and reply to client support tickets.</p>
                <div className="mt-5">
                    <a href={routes?.tickets} data-native="true" className="rounded-full bg-teal-500 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-400">
                        View tickets
                    </a>
                </div>
            </div>
        </>
    );
}
