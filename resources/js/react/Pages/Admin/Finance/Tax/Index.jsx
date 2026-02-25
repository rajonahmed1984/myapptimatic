import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const statusClass = (isActive) => (
    isActive
        ? 'border-emerald-200 text-emerald-700 bg-emerald-50'
        : 'border-slate-300 text-slate-600 bg-slate-50'
);

export default function Index({
    pageTitle = 'Tax Settings',
    heading = 'Tax settings',
    subheading = 'Configure tax mode, default rates, and invoice notes.',
    routes = {},
    settings_form = {},
    rate_form = {},
    quick_reference = {},
    rate_options = [],
    rates = [],
}) {
    const { csrf_token: csrfToken = '', errors = {} } = usePage().props || {};

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="section-label">Finance</div>
                    <div className="text-2xl font-semibold text-slate-900">{heading}</div>
                    <div className="mt-1 text-sm text-slate-500">{subheading}</div>
                </div>
                <a
                    href={routes?.reports}
                    data-native="true"
                    className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                >
                    View Reports
                </a>
            </div>

            <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                <div className="card p-6">
                    <div className="section-label">Settings</div>
                    <form method="POST" action={routes?.settings_update} className="mt-4 grid gap-4 text-sm" data-native="true">
                        <input type="hidden" name="_token" value={csrfToken} />
                        <input type="hidden" name="_method" value="PUT" />
                        <div className="flex items-center gap-2">
                            <input type="hidden" name="enabled" value="0" />
                            <input
                                type="checkbox"
                                name="enabled"
                                value="1"
                                className="h-4 w-4 rounded border-slate-300 text-emerald-600"
                                defaultChecked={Boolean(settings_form?.enabled)}
                            />
                            <span className="text-xs text-slate-600">Enable tax system</span>
                        </div>

                        <div>
                            <label className="text-xs text-slate-500">Default tax mode</label>
                            <select
                                name="tax_mode_default"
                                defaultValue={settings_form?.tax_mode_default || 'exclusive'}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            >
                                <option value="exclusive">Exclusive (add tax on top)</option>
                                <option value="inclusive">Inclusive (included in total)</option>
                            </select>
                            {errors.tax_mode_default ? <div className="mt-1 text-xs text-rose-600">{errors.tax_mode_default}</div> : null}
                        </div>

                        <div>
                            <label className="text-xs text-slate-500">Default tax rate</label>
                            <select
                                name="default_tax_rate_id"
                                defaultValue={settings_form?.default_tax_rate_id || ''}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            >
                                <option value="">No default</option>
                                {rate_options.map((rate) => (
                                    <option key={rate.id} value={rate.id}>
                                        {rate.label}
                                    </option>
                                ))}
                            </select>
                            {errors.default_tax_rate_id ? <div className="mt-1 text-xs text-rose-600">{errors.default_tax_rate_id}</div> : null}
                        </div>

                        <div>
                            <label className="text-xs text-slate-500">Invoice tax label</label>
                            <input
                                name="invoice_tax_label"
                                defaultValue={settings_form?.invoice_tax_label || ''}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            />
                            {errors.invoice_tax_label ? <div className="mt-1 text-xs text-rose-600">{errors.invoice_tax_label}</div> : null}
                        </div>

                        <div>
                            <label className="text-xs text-slate-500">Invoice tax note template</label>
                            <textarea
                                name="invoice_tax_note_template"
                                rows={3}
                                defaultValue={settings_form?.invoice_tax_note_template || ''}
                                className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                            />
                            <div className="mt-1 text-xs text-slate-500">Use {'{rate}'} to display the applied percentage.</div>
                            {errors.invoice_tax_note_template ? <div className="mt-1 text-xs text-rose-600">{errors.invoice_tax_note_template}</div> : null}
                        </div>

                        <div className="flex justify-end">
                            <button type="submit" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>

                <div className="card p-6">
                    <div className="section-label">Quick reference</div>
                    <div className="mt-3 space-y-2 text-sm text-slate-600">
                        <div>
                            Default mode: <span className="font-semibold text-slate-900">{quick_reference?.mode || '--'}</span>
                        </div>
                        <div>
                            Default rate: <span className="font-semibold text-slate-900">{quick_reference?.default_rate_name || 'None'}</span>
                        </div>
                        <div>
                            Invoices label: <span className="font-semibold text-slate-900">{quick_reference?.invoice_label || '--'}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div className="mt-6 card p-6">
                <div className="section-label">Tax rates</div>
                <form method="POST" action={routes?.rate_store} className="mt-4 grid gap-3 text-sm md:grid-cols-6" data-native="true">
                    <input type="hidden" name="_token" value={csrfToken} />
                    <div className="md:col-span-2">
                        <input
                            name="name"
                            defaultValue={rate_form?.name || ''}
                            placeholder="Rate name"
                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                        />
                        {errors.name ? <div className="mt-1 text-xs text-rose-600">{errors.name}</div> : null}
                    </div>
                    <div className="md:col-span-1">
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            name="rate_percent"
                            defaultValue={rate_form?.rate_percent || ''}
                            placeholder="Rate %"
                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                        />
                        {errors.rate_percent ? <div className="mt-1 text-xs text-rose-600">{errors.rate_percent}</div> : null}
                    </div>
                    <div className="md:col-span-1">
                        <input
                            type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                            name="effective_from"
                            defaultValue={rate_form?.effective_from || ''}
                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                        />
                        {errors.effective_from ? <div className="mt-1 text-xs text-rose-600">{errors.effective_from}</div> : null}
                    </div>
                    <div className="md:col-span-1">
                        <input
                            type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                            name="effective_to"
                            defaultValue={rate_form?.effective_to || ''}
                            className="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm"
                        />
                        {errors.effective_to ? <div className="mt-1 text-xs text-rose-600">{errors.effective_to}</div> : null}
                    </div>
                    <div className="md:col-span-1 flex items-center gap-2">
                        <input type="hidden" name="is_active" value="0" />
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            className="h-4 w-4 rounded border-slate-300 text-emerald-600"
                            defaultChecked={Boolean(rate_form?.is_active)}
                        />
                        <span className="text-xs text-slate-600">Active</span>
                    </div>
                    <div className="md:col-span-6">
                        <button type="submit" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                            Add Rate
                        </button>
                    </div>
                </form>

                <div className="mt-6 overflow-x-auto rounded-2xl border border-slate-300 bg-white/80">
                    <table className="min-w-full text-sm text-slate-700">
                        <thead>
                            <tr className="text-xs uppercase tracking-[0.2em] text-slate-500">
                                <th className="py-2 px-3">Name</th>
                                <th className="py-2 px-3">Rate</th>
                                <th className="py-2 px-3">Effective</th>
                                <th className="py-2 px-3">Status</th>
                                <th className="py-2 px-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rates.length > 0 ? (
                                rates.map((rate) => (
                                    <tr key={rate.id} className="border-b border-slate-100">
                                        <td className="py-2 px-3 font-semibold text-slate-900">{rate.name}</td>
                                        <td className="py-2 px-3">{rate.rate_percent_display}</td>
                                        <td className="py-2 px-3">
                                            <div>{rate.effective_from_display}</div>
                                            <div className="text-xs text-slate-500">to {rate.effective_to_display}</div>
                                        </td>
                                        <td className="py-2 px-3">
                                            <span className={`rounded-full border px-2 py-0.5 text-xs font-semibold ${statusClass(rate.is_active)}`}>
                                                {rate.status_label}
                                            </span>
                                        </td>
                                        <td className="py-2 px-3 text-right">
                                            <div className="flex justify-end gap-3 text-xs font-semibold">
                                                <a href={rate?.routes?.edit} data-native="true" className="text-teal-600 hover:text-teal-500">
                                                    Edit
                                                </a>
                                                <form
                                                    method="POST"
                                                    action={rate?.routes?.destroy}
                                                    data-native="true"
                                                    data-delete-confirm
                                                    data-confirm-name={rate.confirm_name}
                                                    data-confirm-title={`Delete tax rate ${rate.confirm_name}?`}
                                                    data-confirm-description="This will permanently delete the tax rate."
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
                                    <td colSpan={5} className="py-4 px-3 text-center text-slate-500">
                                        No tax rates yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
