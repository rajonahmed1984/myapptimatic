import React from 'react';
import { Head } from '@inertiajs/react';

export default function Edit({ pageTitle = 'Edit Payroll Period', period = {}, routes = {} }) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">HR</div>
                    <div className="text-2xl font-semibold text-slate-900">Edit Payroll Period {period.period_key}</div>
                    <div className="text-sm text-slate-500">Only draft payroll periods can be edited.</div>
                </div>
                <a href={routes?.index} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-600">Back</a>
            </div>

            <div className="card p-6 max-w-2xl">
                <form method="POST" action={routes?.update} data-native="true" className="grid gap-4 md:grid-cols-2">
                    <input type="hidden" name="_token" value={token} />
                    <input type="hidden" name="_method" value="PUT" />
                    <div>
                        <label htmlFor="periodKey" className="text-xs uppercase tracking-[0.2em] text-slate-500">Period Key</label>
                        <input id="periodKey" name="period_key" defaultValue={period?.period_key || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" placeholder="YYYY-MM" required />
                    </div>
                    <div>
                        <label htmlFor="startDate" className="text-xs uppercase tracking-[0.2em] text-slate-500">Start Date</label>
                        <input id="startDate" type="date" name="start_date" defaultValue={period?.start_date || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required />
                    </div>
                    <div>
                        <label htmlFor="endDate" className="text-xs uppercase tracking-[0.2em] text-slate-500">End Date</label>
                        <input id="endDate" type="date" name="end_date" defaultValue={period?.end_date || ''} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" required />
                    </div>
                    <div className="md:col-span-2 flex items-center gap-3">
                        <button type="submit" className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Save Changes</button>
                        <a href={routes?.index} data-native="true" className="text-sm font-semibold text-slate-600 hover:text-slate-900">Cancel</a>
                    </div>
                </form>
            </div>
        </>
    );
}
