import React from 'react';
import { Head } from '@inertiajs/react';

export default function Index({ has_customer = false, subscriptions = [], routes = {} }) {
    return (
        <>
            <Head title="Services" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Services</h1>
                    <p className="mt-1 text-sm text-slate-500">Review active services and billing cycle details.</p>
                </div>
                <a href={routes.dashboard} data-native="true" className="text-sm text-slate-500 hover:text-teal-600">
                    Back to dashboard
                </a>
            </div>

            {!has_customer ? (
                <div className="card p-6 text-sm text-slate-600">
                    Your account is not linked to a customer profile yet. Please contact support.
                </div>
            ) : subscriptions.length === 0 ? (
                <div className="card p-6 text-sm text-slate-500">No active services found.</div>
            ) : (
                <div className="card overflow-hidden">
                    <table className="w-full min-w-[820px] text-left text-sm">
                        <thead className="border-b border-slate-200 text-xs uppercase tracking-[0.25em] text-slate-500">
                            <tr>
                                <th className="px-4 py-3">SL</th>
                                <th className="px-4 py-3">Service</th>
                                <th className="px-4 py-3">Plan</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Cycle</th>
                                <th className="px-4 py-3">Next Due</th>
                                <th className="px-4 py-3">Auto Renew</th>
                                <th className="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {subscriptions.map((subscription) => (
                                <tr key={subscription.id} className="border-b border-slate-100">
                                    <td className="px-4 py-3 text-slate-600">{subscription.serial}</td>
                                    <td className="px-4 py-3 font-medium text-slate-900">{subscription.service_name}</td>
                                    <td className="px-4 py-3 text-slate-600">{subscription.plan_name}</td>
                                    <td className="px-4 py-3 text-slate-600">{subscription.status_label}</td>
                                    <td className="px-4 py-3 text-slate-500">{subscription.cycle_label}</td>
                                    <td className="px-4 py-3 text-slate-500">{subscription.next_due_display}</td>
                                    <td className="px-4 py-3 text-slate-500">{subscription.auto_renew_label}</td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-3 text-xs">
                                            <a href={subscription.routes.show} data-native="true" className="text-teal-600 hover:text-teal-500">
                                                View
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </>
    );
}
