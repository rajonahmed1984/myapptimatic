import React from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Index({
    pageTitle = 'Payment Methods',
    form = {},
    methods = [],
}) {
    const page = usePage();
    const csrfToken = page?.props?.csrf_token || '';
    const errors = page?.props?.errors || {};
    const hasErrors = Object.keys(errors).length > 0;

    const confirmDelete = () => window.confirm('Delete this payment method?');

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Finance</div>
                    <h1 className="mt-2 text-2xl font-semibold text-slate-900">Payment methods</h1>
                    <p className="mt-1 text-sm text-slate-500">Manage payout/payment accounts for all finance forms.</p>
                </div>
            </div>

            <div className="grid gap-6 lg:grid-cols-10">
                <div className="card p-6 lg:col-span-3">
                    <div className="text-sm font-semibold text-slate-900">{form?.title || 'Add payment method'}</div>

                    {hasErrors ? (
                        <div className="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700">
                            Please fix the validation errors and try again.
                        </div>
                    ) : null}

                    <form method="POST" action={form?.action} className="mt-4 grid gap-4 text-sm" data-native="true">
                        <input type="hidden" name="_token" value={csrfToken} />
                        {String(form?.method || 'POST').toUpperCase() !== 'POST' ? (
                            <input type="hidden" name="_method" value={String(form?.method || 'POST').toUpperCase()} />
                        ) : null}

                        <div>
                            <label className="text-xs text-slate-500">Name</label>
                            <input
                                name="name"
                                defaultValue={form?.fields?.name || ''}
                                className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2"
                                required
                            />
                        </div>
                        <div>
                            <label className="text-xs text-slate-500">Code</label>
                            <input
                                name="code"
                                defaultValue={form?.fields?.code || ''}
                                className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2"
                                placeholder="bank-transfer"
                            />
                        </div>
                        <div>
                            <label className="text-xs text-slate-500">Sort order</label>
                            <input
                                type="number"
                                min="0"
                                name="sort_order"
                                defaultValue={form?.fields?.sort_order ?? 0}
                                className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2"
                            />
                        </div>
                        <div>
                            <label className="text-xs text-slate-500">Account details</label>
                            <textarea
                                name="account_details"
                                rows={3}
                                defaultValue={form?.fields?.account_details || ''}
                                className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2"
                                placeholder="Account number, wallet number, branch, etc."
                            />
                        </div>
                        <label className="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="hidden" name="is_active" value="0" />
                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                defaultChecked={Boolean(form?.fields?.is_active)}
                                className="rounded border-slate-300 text-emerald-600"
                            />
                            Active
                        </label>
                        <div className="flex items-center gap-3">
                            <button type="submit" className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                                {String(form?.method || 'POST').toUpperCase() === 'PUT' ? 'Update method' : 'Add method'}
                            </button>
                            {form?.cancel_href ? (
                                <a href={form.cancel_href} data-native="true" className="text-xs font-semibold text-slate-600 hover:text-slate-800">
                                    Cancel edit
                                </a>
                            ) : null}
                        </div>
                    </form>
                </div>

                <div className="card p-6 lg:col-span-7">
                    <div className="text-sm font-semibold text-slate-900">Method list</div>
                    <div className="mt-4 overflow-x-auto rounded-xl border border-slate-200">
                        <table className="min-w-full text-sm text-slate-700">
                            <thead className="bg-slate-50 text-left text-xs uppercase tracking-[0.2em] text-slate-500">
                                <tr>
                                    <th className="px-3 py-2">Name</th>
                                    <th className="px-3 py-2">Code</th>
                                    <th className="px-3 py-2">Amount</th>
                                    <th className="px-3 py-2">Details</th>
                                    <th className="px-3 py-2">Status</th>
                                    <th className="px-3 py-2">Order</th>
                                    <th className="px-3 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {methods.length > 0 ? (
                                    methods.map((method) => (
                                        <tr key={method.id} className="border-t border-slate-200">
                                            <td className="px-3 py-2 font-medium text-slate-900">{method.name}</td>
                                            <td className="px-3 py-2">{method.code}</td>
                                            <td className="px-3 py-2 font-medium text-slate-800">{method.amount_display}</td>
                                            <td className="px-3 py-2 text-xs text-slate-600">{method.account_details}</td>
                                            <td className="px-3 py-2">
                                                <span
                                                    className={`rounded-full px-2 py-0.5 text-xs font-semibold ${
                                                        method.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700'
                                                    }`}
                                                >
                                                    {method.is_active ? 'Active' : 'Inactive'}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2">{method.sort_order}</td>
                                            <td className="px-3 py-2 text-right">
                                                <div className="inline-flex items-center gap-3">
                                                    <a
                                                        href={method?.routes?.show}
                                                        data-native="true"
                                                        className="text-slate-700 hover:text-slate-900"
                                                    >
                                                        View
                                                    </a>
                                                    <a
                                                        href={method?.routes?.edit}
                                                        data-native="true"
                                                        className="text-teal-600 hover:text-teal-500"
                                                    >
                                                        Edit
                                                    </a>
                                                    <form
                                                        method="POST"
                                                        action={method?.routes?.destroy}
                                                        data-native="true"
                                                        onSubmit={(event) => {
                                                            if (!confirmDelete()) {
                                                                event.preventDefault();
                                                            }
                                                        }}
                                                    >
                                                        <input type="hidden" name="_token" value={csrfToken} />
                                                        <input type="hidden" name="_method" value="DELETE" />
                                                        <button type="submit" className="text-rose-600 hover:text-rose-500">
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={7} className="px-3 py-4 text-center text-slate-500">
                                            No payment methods found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
