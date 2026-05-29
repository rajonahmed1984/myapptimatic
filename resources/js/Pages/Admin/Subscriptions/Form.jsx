import React, { useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';

function LicenseCard({ license, csrf, statusClass }) {
    const [editOpen, setEditOpen] = useState(false);
    const [licenseKey, setLicenseKey] = useState(license.fields.license_key || '');
    const [domain, setDomain] = useState(license.fields.allowed_domains || '');
    const inputClass = 'w-full text-xs px-4 py-1.5 h-8 rounded-full border border-slate-300 focus:outline-none focus:ring-1 focus:ring-teal-600';

    return (
        <div className="rounded-xl border border-slate-200 p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-sm font-medium text-slate-700">#{license.id} — {license.product_name}</span>
                    <span className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium capitalize ${statusClass(license.fields.status)}`}>
                        {license.fields.status}
                    </span>
                    {license.fields.license_key && (
                        <span className="font-mono text-xs text-slate-500">{license.fields.license_key}</span>
                    )}
                    {license.fields.allowed_domains && (
                        <span className="text-xs text-slate-500">{license.fields.allowed_domains}</span>
                    )}
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        onClick={() => setEditOpen((v) => !v)}
                        className="rounded-full border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                    >
                        {editOpen ? 'Close' : 'Edit Key / Domain'}
                    </button>
                    {license.fields.status !== 'suspended' && license.fields.status !== 'revoked' && (
                        <form action={license.routes.suspend} method="POST" data-native="true">
                            <input type="hidden" name="_token" value={csrf} />
                            <input type="hidden" name="return_to_subscription" value="1" />
                            <button type="submit" className="rounded-full border border-amber-300 px-3 py-1.5 text-xs font-semibold text-amber-600 hover:bg-amber-50">
                                Suspend
                            </button>
                        </form>
                    )}
                    {license.fields.status === 'suspended' && (
                        <form action={license.routes.unsuspend} method="POST" data-native="true">
                            <input type="hidden" name="_token" value={csrf} />
                            <input type="hidden" name="return_to_subscription" value="1" />
                            <button type="submit" className="rounded-full border border-teal-300 px-3 py-1.5 text-xs font-semibold text-teal-600 hover:bg-teal-50">
                                Unsuspend
                            </button>
                        </form>
                    )}
                    {license.fields.status !== 'revoked' && (
                        <form
                            action={license.routes.terminate}
                            method="POST"
                            data-native="true"
                            onSubmit={(e) => { if (!window.confirm('Terminate this license? The status will be set to revoked.')) e.preventDefault(); }}
                        >
                            <input type="hidden" name="_token" value={csrf} />
                            <input type="hidden" name="return_to_subscription" value="1" />
                            <button type="submit" className="rounded-full border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">
                                Terminate
                            </button>
                        </form>
                    )}
                </div>
            </div>

            {editOpen && (
                <form action={license.form.action} method="POST" data-native="true" className="mt-4 border-t border-slate-100 pt-4">
                    <input type="hidden" name="_token" value={csrf} />
                    <input type="hidden" name="_method" value={license.form.method} />
                    <input type="hidden" name="return_to_subscription" value="1" />
                    <input type="hidden" name="subscription_id" value={license.fields.subscription_id} />
                    <input type="hidden" name="product_id" value={license.fields.product_id} />
                    <input type="hidden" name="status" value={license.fields.status} />
                    <input type="hidden" name="starts_at" value={license.fields.starts_at} />
                    <input type="hidden" name="expires_at" value={license.fields.expires_at} />
                    <input type="hidden" name="auto_suspend_override_until" value={license.fields.auto_suspend_override_until} />
                    <input type="hidden" name="notes" value={license.fields.notes} />
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-700">License Key</label>
                            <input
                                type="text"
                                name="license_key"
                                value={licenseKey}
                                onChange={(e) => setLicenseKey(e.target.value)}
                                className={inputClass}
                                placeholder="Leave blank to keep existing"
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-700">License URL / Domain</label>
                            <input
                                type="text"
                                name="allowed_domains"
                                value={domain}
                                onChange={(e) => setDomain(e.target.value)}
                                className={inputClass}
                                placeholder="e.g. example.com"
                            />
                        </div>
                    </div>
                    <div className="mt-3">
                        <button type="submit" className="rounded-full bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-500">
                            Save License
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
}

export default function Form({
    pageTitle = 'Subscription',
    is_edit = false,
    customers = [],
    plans = [],
    sales_reps = [],
    form = {},
    routes = {},
    licenseManager = null,
}) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const fields = form?.fields || {};
    const [selectedPlanId, setSelectedPlanId] = useState(String(fields?.plan_id || ''));
    const [selectedSalesRepId, setSelectedSalesRepId] = useState(String(fields?.sales_rep_id || ''));
    const [commissionPercent, setCommissionPercent] = useState(String(fields?.sales_rep_commission_percent || ''));
    const hasSelectedSalesRep = selectedSalesRepId !== '';
    const customerOptions = useMemo(
        () => [
            { value: '', label: 'Select customer' },
            ...customers.map((customer) => ({
                value: String(customer.id),
                label: customer.name,
            })),
        ],
        [customers],
    );
    const planOptions = useMemo(
        () => [
            { value: '', label: 'Select plan' },
            ...plans.map((plan) => ({
                value: String(plan.id),
                label: `${plan.product_name} - ${plan.name} (${plan.interval})`,
            })),
        ],
        [plans],
    );
    const salesRepOptions = useMemo(
        () => [
            { value: '', label: 'None' },
            ...sales_reps.map((rep) => ({
                value: String(rep.id),
                label: `${rep.name} (${rep.status})`,
            })),
        ],
        [sales_reps],
    );
    const statusOptions = useMemo(
        () => [
            { value: 'active', label: 'Active' },
            { value: 'cancelled', label: 'Cancelled' },
            { value: 'suspended', label: 'Suspended' },
        ],
        [],
    );
    const inputTokenClass = 'w-full text-xs px-4 py-1.5 h-8 rounded-full border border-slate-300 focus:outline-none focus:ring-1 focus:ring-teal-600';
    const selectTokenClass = 'w-full text-xs px-4 py-1.5 h-8 rounded-full border border-slate-300 bg-white focus:outline-none focus:ring-1 focus:ring-teal-600';
    const licenseStatusClass = (status) => {
        if (status === 'active') return 'bg-emerald-100 text-emerald-700';
        if (status === 'suspended') return 'bg-amber-100 text-amber-700';
        if (status === 'revoked') return 'bg-rose-100 text-rose-700';
        return 'bg-slate-100 text-slate-600';
    };
    const planById = useMemo(() => {
        const map = {};
        plans.forEach((plan) => {
            map[String(plan.id)] = plan;
        });

        return map;
    }, [plans]);

    const formatAmount = (value) => {
        const numericValue = Number(value);
        if (!Number.isFinite(numericValue)) {
            return '';
        }

        return numericValue.toFixed(2);
    };

    const [subscriptionAmount, setSubscriptionAmount] = useState(() => {
        const fieldValue = fields?.subscription_amount;
        if (fieldValue !== null && fieldValue !== undefined && String(fieldValue).trim() !== '') {
            return String(fieldValue);
        }

        const initialPlan = planById[String(fields?.plan_id || '')];
        return initialPlan ? formatAmount(initialPlan.price) : '';
    });

    const selectedPlan = planById[selectedPlanId] || null;
    const commissionAmountPreview = useMemo(() => {
        if (String(commissionPercent).trim() === '') {
            return '';
        }

        const percentValue = Number(commissionPercent);
        const amountValue = Number(subscriptionAmount);

        if (!Number.isFinite(percentValue) || !Number.isFinite(amountValue) || percentValue < 0 || amountValue < 0) {
            return '';
        }

        return ((amountValue * percentValue) / 100).toFixed(2);
    }, [commissionPercent, subscriptionAmount]);

    return (
        <>
            <Head title={pageTitle} />
            <div className="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-6">
                <div className="mb-6 flex items-center justify-between">
                    <h1 className="text-xl font-semibold text-slate-900">{pageTitle}</h1>
                    <a href={routes?.index} data-native="true" className="text-sm font-medium text-teal-600 hover:text-teal-500">
                        Back to list
                    </a>
                </div>

                <form action={form?.action} method="POST" data-native="true" className="space-y-4">
                    <input type="hidden" name="_token" value={csrf} />
                    {String(form?.method || 'POST').toUpperCase() !== 'POST' ? (
                        <input type="hidden" name="_method" value={form?.method} />
                    ) : null}

                    <div className="grid gap-4 md:grid-cols-3">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Customer</label>
                            <select
                                name="customer_id"
                                defaultValue={String(fields?.customer_id || '')}
                                className={`${selectTokenClass} mt-2`}
                            >
                                {customerOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                            </select>
                            {errors?.customer_id ? <p className="mt-1 text-xs text-rose-600">{errors.customer_id}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Plan</label>
                            <select
                                name="plan_id"
                                value={selectedPlanId}
                                onChange={(event) => {
                                    const planId = String(event.target.value || '');
                                    setSelectedPlanId(planId);
                                    const plan = planById[planId];
                                    setSubscriptionAmount(plan ? formatAmount(plan.price) : '');
                                }}
                                className={`${selectTokenClass} mt-2`}
                            >
                                {planOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                            </select>
                            {errors?.plan_id ? <p className="mt-1 text-xs text-rose-600">{errors.plan_id}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Subscription Amount</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="subscription_amount"
                                value={subscriptionAmount}
                                onChange={(event) => setSubscriptionAmount(event.target.value)}
                                className={inputTokenClass}
                                placeholder="0.00"
                            />
                            <p className="mt-1 text-xs text-slate-500">
                                Auto from selected plan, but you can edit manually.
                                {selectedPlan ? ` (${selectedPlan.currency ? `${selectedPlan.currency} ` : ''}${formatAmount(selectedPlan.price)})` : ''}
                            </p>
                            {errors?.subscription_amount ? <p className="mt-1 text-xs text-rose-600">{errors.subscription_amount}</p> : null}
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-4">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Start Date</label>
                            <input type="text" placeholder="DD-MM-YYYY" inputMode="numeric" name="start_date" defaultValue={fields?.start_date || ''} className={inputTokenClass} />
                            {errors?.start_date ? <p className="mt-1 text-xs text-rose-600">{errors.start_date}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Current Period Start</label>
                            <input
                                type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                name="current_period_start"
                                defaultValue={fields?.current_period_start || ''}
                                className={inputTokenClass}
                            />
                            {errors?.current_period_start ? <p className="mt-1 text-xs text-rose-600">{errors.current_period_start}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Current Period End</label>
                            <input
                                type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                name="current_period_end"
                                defaultValue={fields?.current_period_end || ''}
                                className={inputTokenClass}
                            />
                            {errors?.current_period_end ? <p className="mt-1 text-xs text-rose-600">{errors.current_period_end}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Next Invoice Date</label>
                            <input
                                type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                name="next_invoice_at"
                                defaultValue={fields?.next_invoice_at || ''}
                                className={inputTokenClass}
                            />
                            {errors?.next_invoice_at ? <p className="mt-1 text-xs text-rose-600">{errors.next_invoice_at}</p> : null}
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-4">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Access Override Until</label>
                            <input
                                type="text" placeholder="DD-MM-YYYY" inputMode="numeric"
                                name="access_override_until"
                                defaultValue={fields?.access_override_until || ''}
                                className={inputTokenClass}
                            />
                            {errors?.access_override_until ? <p className="mt-1 text-xs text-rose-600">{errors.access_override_until}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Sales Rep</label>
                            <select
                                name="sales_rep_id"
                                value={selectedSalesRepId}
                                onChange={(event) => setSelectedSalesRepId(String(event.target.value || ''))}
                                className={`${selectTokenClass} mt-2`}
                            >
                                {salesRepOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                            </select>
                            {errors?.sales_rep_id ? <p className="mt-1 text-xs text-rose-600">{errors.sales_rep_id}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Sales Rep Commission (%)</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                max="100"
                                name="sales_rep_commission_percent"
                                value={commissionPercent}
                                onChange={(event) => setCommissionPercent(event.target.value)}
                                className={`${inputTokenClass} disabled:bg-slate-100 disabled:text-slate-400`}
                                placeholder="0.00"
                                disabled={!hasSelectedSalesRep}
                            />
                            <p className="mt-1 text-xs text-slate-500">
                                {!hasSelectedSalesRep
                                    ? 'Select sales rep first.'
                                    : commissionAmountPreview !== ''
                                        ? `Commission amount: ${selectedPlan?.currency ? `${selectedPlan.currency} ` : ''}${commissionAmountPreview}`
                                        : 'Enter percentage (0-100).'}
                            </p>
                            {errors?.sales_rep_commission_percent ? <p className="mt-1 text-xs text-rose-600">{errors.sales_rep_commission_percent}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Status</label>
                            <select
                                name="status"
                                defaultValue={String(fields?.status || 'active')}
                                className={`${selectTokenClass} mt-2`}
                            >
                                {statusOptions.map((option) => <option key={option.value} value={option.value}>{option.label}</option>)}
                            </select>
                            {errors?.status ? <p className="mt-1 text-xs text-rose-600">{errors.status}</p> : null}
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Notes</label>
                        <textarea name="notes" rows={1} defaultValue={fields?.notes || ''} className="w-full rounded-full border border-slate-300 px-4 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-teal-600" />
                    </div>

                    <div className="flex flex-wrap items-center gap-5">
                        <label className="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="hidden" name="auto_renew" value="0" />
                            <input type="checkbox" name="auto_renew" value="1" defaultChecked={Boolean(fields?.auto_renew)} />
                            Auto renew
                        </label>
                        <label className="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="hidden" name="cancel_at_period_end" value="0" />
                            <input type="checkbox" name="cancel_at_period_end" value="1" defaultChecked={Boolean(fields?.cancel_at_period_end)} />
                            Cancel at period end
                        </label>
                    </div>

                    <div className="flex items-center gap-3 pt-2">
                        <button type="submit" className="bg-teal-600 rounded-full text-xs px-3 py-1.5 font-semibold text-white hover:bg-teal-500">
                            {is_edit ? 'Update Subscription' : 'Create Subscription'}
                        </button>
                        <a href={routes?.index} data-native="true" className="border border-slate-300 rounded-full text-xs px-3 py-1.5 font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

            {is_edit && licenseManager?.licenses?.length > 0 && (
                <div className="mx-auto mt-6 max-w-4xl rounded-2xl border border-slate-200 bg-white p-6">
                    <h2 className="mb-4 text-base font-semibold text-slate-800">Licenses</h2>
                    <div className="space-y-3">
                        {licenseManager.licenses.map((license) => (
                            <LicenseCard key={license.id} license={license} csrf={csrf} statusClass={licenseStatusClass} />
                        ))}
                    </div>
                </div>
            )}
        </>
    );
}
