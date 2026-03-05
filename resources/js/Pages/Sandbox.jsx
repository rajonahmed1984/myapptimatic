import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Sandbox({ generated_at }) {
    const { app, auth, features } = usePage().props;

    return (
        <>
            <Head title="React Sandbox" />
            <main className="mx-auto max-w-3xl p-6 sm:p-10">
                <section className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h1 className="text-2xl font-semibold text-slate-900">React Sandbox</h1>
                    <p className="mt-2 text-sm text-slate-600">
                        This route is isolated and feature-flagged. Existing Blade routes remain unchanged.
                    </p>
                    <dl className="mt-6 space-y-2 text-sm text-slate-700">
                        <div className="flex gap-2">
                            <dt className="font-medium">App:</dt>
                            <dd>{app?.name ?? '-'}</dd>
                        </div>
                        <div className="flex gap-2">
                            <dt className="font-medium">User:</dt>
                            <dd>{auth?.user?.email ?? 'guest'}</dd>
                        </div>
                        <div className="flex gap-2">
                            <dt className="font-medium">Flag:</dt>
                            <dd>{features?.react_sandbox ? 'ON' : 'OFF'}</dd>
                        </div>
                        <div className="flex gap-2">
                            <dt className="font-medium">Generated:</dt>
                            <dd>{generated_at}</dd>
                        </div>
                    </dl>
                </section>
            </main>
        </>
    );
}
