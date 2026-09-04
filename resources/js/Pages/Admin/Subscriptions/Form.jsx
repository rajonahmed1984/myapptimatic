import React, { useMemo, useState } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import SearchableSelect from '../../../Components/SearchableSelect';

function LicenseCard({ license, csrf, statusClass, moveTargets = [] }) {
    const [editOpen, setEditOpen] = useState(false);
    const [moveOpen, setMoveOpen] = useState(false);
    const [licenseKey, setLicenseKey] = useState(license.fields.license_key || '');
    const [domain, setDomain] = useState(license.fields.allowed_domains || '');
    const inputClass = 'w-full text-xs px-4 py-1.5 h-8 rounded-[10px] border border-slate-300 focus:outline-none focus:ring-1 focus:ring-teal-600';

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
                    {moveTargets.length > 0 && license.routes?.move ? (
                        <button
                            type="button"
                            onClick={() => setMoveOpen((v) => !v)}
                            className="rounded-full border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-indigo-300 hover:text-indigo-600"
                        >
                            {moveOpen ? 'Close' : 'Move'}
                        </button>
                    ) : null}
                    {license.fields.status !== 'suspended' && license.fields.status !== 'revoked' && (
                        <button
                            type="submit"
                            form={`suspend-form-${license.id}`}
                            className="rounded-full border border-amber-300 px-3 py-1.5 text-xs font-semibold text-amber-600 hover:bg-amber-50"
                        >
                            Suspend
                        </button>
                    )}
                    {license.fields.status === 'suspended' && (
                        <button
                            type="submit"
                            form={`unsuspend-form-${license.id}`}
                            className="rounded-full border border-teal-300 px-3 py-1.5 text-xs font-semibold text-teal-600 hover:bg-teal-50"
                        >
                            Unsuspend
                        </button>
                    )}
                    {(license.fields.status === 'revoked' || license.fields.status === 'expired') && (
                        <button
                            type="submit"
                            form={`reactivate-form-${license.id}`}
                            className="rounded-full border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-600 hover:bg-emerald-50"
                        >
                            Reactivate
                        </button>
                    )}
                    {license.fields.status !== 'revoked' && (
                        <button
                            type="submit"
                            form={`terminate-form-${license.id}`}
                            className="rounded-full border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50"
                        >
                            Terminate
                        </button>
                    )}
                    <button
                        type="submit"
                        form={`reissue-key-form-${license.id}`}
                        onClick={(e) => {
                            if (!window.confirm('Reissue this license key? The previous key will stop working immediately.')) {
                                e.preventDefault();
                            }
                        }}
                        className="rounded-full border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                    >
                        Reissue Key
                    </button>
                    {license.certificate ? (
                        <button
                            type="submit"
                            form={`certificate-revoke-form-${license.id}`}
                            onClick={(e) => {
                                if (!window.confirm('Revoke the active offline license certificate?')) {
                                    e.preventDefault();
                                }
                            }}
                            className="rounded-full border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50"
                            title={`Issued ${license.certificate.issued_at}`}
                        >
                            Revoke Certificate
                        </button>
                    ) : (
                        <button
                            type="submit"
                            form={`certificate-issue-form-${license.id}`}
                            className="rounded-full border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-600"
                        >
                            Issue Certificate
                        </button>
                    )}
                </div>
            </div>

            {moveOpen && (
                <form
                    action={license.routes.move}
                    method="POST"
                    data-native="true"
                    className="mt-4 border-t border-slate-100 pt-4"
                    onSubmit={(e) => {
                        if (!window.confirm('Move this license to the selected subscription? The new owner will be billed for it from now on.')) {
                            e.preventDefault();
                        }
                    }}
                >
                    <input type="hidden" name="_token" value={csrf} />
                    <input type="hidden" name="_method" value="PUT" />
                    <input type="hidden" name="return_to_subscription" value="1" />
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-700">Move to subscription</label>
                            <SearchableSelect
                                name="subscription_id"
                                options={moveTargets}
                                placeholder="Choose the destination subscription..."
                                required
                            />
                            <p className="mt-1 text-xs text-slate-400">
                                The license leaves this subscription and is billed under the destination client instead.
                            </p>
                        </div>
                        <div className="flex flex-col justify-between gap-3">
                            <label className="inline-flex items-start gap-2 text-xs text-slate-700">
                                <input type="hidden" name="keep_domains" value="0" />
                                <input type="checkbox" name="keep_domains" value="1" className="mt-0.5" />
                                <span>Keep the current domain bindings. Leave this off when the new owner runs the software somewhere else — the existing domains are then revoked.</span>
                            </label>
                            <button
                                type="submit"
                                className="self-start rounded-full bg-indigo-600 px-4 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500"
                            >
                                Move License
                            </button>
                        </div>
                    </div>
                </form>
            )}

            {editOpen && (
                <div className="mt-4 border-t border-slate-100 pt-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-xs font-medium text-slate-700">License Key</label>
                            <input
                                type="text"
                                name="license_key"
                                form={`edit-form-${license.id}`}
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
                                form={`edit-form-${license.id}`}
                                value={domain}
                                onChange={(e) => setDomain(e.target.value)}
                                className={inputClass}
                                placeholder="e.g. example.com"
                            />
                        </div>
                    </div>
                    <div className="mt-3">
                        <button
                            type="submit"
                            form={`edit-form-${license.id}`}
                            className="rounded-full bg-teal-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-500"
                        >
                            Save License
                        </button>
                    </div>
                </div>
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
    provision = null,
    secret_configured = false,
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
                company_name: customer.company_name,
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
    const inputTokenClass = 'w-full text-xs px-4 py-1.5 h-8 rounded-[10px] border border-slate-300 focus:outline-none focus:ring-1 focus:ring-teal-600';
    const selectTokenClass = 'w-full text-xs pl-4 pr-10 py-1.5 h-8 rounded-[10px] border border-slate-300 bg-white focus:outline-none focus:ring-1 focus:ring-teal-600';
    const licenseStatusClass = (status) => {
        if (status === 'active') return 'bg-emerald-100 text-emerald-700';
        if (status === 'suspended') return 'bg-amber-100 text-amber-700';
        if (status === 'expired') return 'bg-orange-100 text-orange-700';
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

    const selectedPlan = planById[selectedPlanId] || null;
    const isPerFlat = Boolean(selectedPlan?.is_per_flat || selectedPlan?.pricing_model === 'per_flat');

    const [contractedFlats, setContractedFlats] = useState(() => {
        if (fields?.contracted_flats !== undefined && fields?.contracted_flats !== null && String(fields.contracted_flats).trim() !== '') {
            return String(fields.contracted_flats);
        }
        if (provision?.contracted_flats) {
            return String(provision.contracted_flats);
        }
        const initialPlan = planById[String(fields?.plan_id || '')];
        const initialAmount = fields?.subscription_amount;
        if (initialPlan?.is_per_flat && initialAmount && Number(initialPlan.price) > 0) {
            return String(Math.round(Number(initialAmount) / Number(initialPlan.price)));
        }
        return '40';
    });

    const [totalFloors, setTotalFloors] = useState(() => {
        return String(fields?.total_floors || provision?.total_floors || '10');
    });

    const [buildingName, setBuildingName] = useState(() => {
        return String(fields?.building_name || provision?.building_name || '');
    });

    const [buildingAddress, setBuildingAddress] = useState(() => {
        return String(fields?.building_address || provision?.building_address || '');
    });

    const [installUrl, setInstallUrl] = useState(() => {
        return String(fields?.install_url || provision?.install_url || 'https://app.mybuilding.com');
    });

    const [isProvisioning, setIsProvisioning] = useState(false);

    const [subscriptionAmount, setSubscriptionAmount] = useState(() => {
        const fieldValue = fields?.subscription_amount;
        if (fieldValue !== null && fieldValue !== undefined && String(fieldValue).trim() !== '') {
            return String(fieldValue);
        }

        const initialPlan = planById[String(fields?.plan_id || '')];
        if (!initialPlan) return '';
        if (initialPlan.is_per_flat || initialPlan.pricing_model === 'per_flat') {
            const flats = Number(contractedFlats) || 40;
            return (flats * Number(initialPlan.price)).toFixed(2);
        }
        return formatAmount(initialPlan.price);
    });

    const handleContractedFlatsChange = (val) => {
        setContractedFlats(val);
        if (isPerFlat && selectedPlan && Number(selectedPlan.price) > 0) {
            const flatsNum = Number(val);
            if (Number.isFinite(flatsNum) && flatsNum >= 0) {
                setSubscriptionAmount((flatsNum * Number(selectedPlan.price)).toFixed(2));
            }
        }
    };

    const handlePlanChange = (newPlanId) => {
        setSelectedPlanId(newPlanId);
        const plan = planById[newPlanId];
        if (plan) {
            if (plan.is_per_flat || plan.pricing_model === 'per_flat') {
                const flats = Number(contractedFlats) || 40;
                setSubscriptionAmount((flats * Number(plan.price)).toFixed(2));
            } else {
                setSubscriptionAmount(formatAmount(plan.price));
            }
        } else {
            setSubscriptionAmount('');
        }
    };

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
            <div className="mx-auto max-w-4x2 rounded-2xl border border-slate-200 bg-white p-6">
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
                                onChange={(event) => handlePlanChange(String(event.target.value || ''))}
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
                                {isPerFlat ? (
                                    <span className="font-medium text-cyan-700">
                                        Calculated from {contractedFlats || 0} flats &times; {selectedPlan?.currency || 'BDT'} {formatAmount(selectedPlan?.price || 50)} / flat
                                    </span>
                                ) : (
                                    <>
                                        Auto from selected plan, but you can edit manually.
                                        {selectedPlan ? ` (${selectedPlan.currency ? `${selectedPlan.currency} ` : ''}${formatAmount(selectedPlan.price)})` : ''}
                                    </>
                                )}
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

                    {/* Building & Flat-wise Pricing Setup */}
                    {isPerFlat && (
                        <div className="rounded-2xl border-2 border-cyan-200 bg-gradient-to-br from-cyan-50/40 via-sky-50/20 to-white p-5 shadow-sm space-y-4">
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-cyan-100 pb-3">
                                <div className="flex items-center gap-2.5">
                                    <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-cyan-600 text-white shadow-xs">
                                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 className="text-base font-semibold text-slate-900">Building & Flat-wise Pricing Configuration</h2>
                                        <p className="text-xs text-slate-500">Flat-based subscription: rate is calculated per flat and synchronized with the building deployment.</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <span className="inline-flex items-center rounded-full bg-cyan-100 px-3 py-1 text-xs font-semibold text-cyan-800 border border-cyan-200">
                                        Per Flat Rate: {selectedPlan?.currency || 'BDT'} {formatAmount(selectedPlan?.price || 50)} / Flat / Month
                                    </span>
                                </div>
                            </div>

                            {/* Live Calculation Pill */}
                            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-900 px-4 py-3 text-white shadow-inner">
                                <div className="flex flex-wrap items-center gap-2 text-sm font-medium">
                                    <span className="rounded-lg bg-slate-800 px-2.5 py-1 text-cyan-300 font-mono font-bold">
                                        {contractedFlats || 0} Flats
                                    </span>
                                    <span className="text-slate-400">&times;</span>
                                    <span className="rounded-lg bg-slate-800 px-2.5 py-1 text-slate-200 font-mono">
                                        {selectedPlan?.currency || 'BDT'} {formatAmount(selectedPlan?.price || 50)}
                                    </span>
                                    <span className="text-slate-400">=</span>
                                    <span className="rounded-lg bg-gradient-to-r from-emerald-500 to-teal-500 px-3 py-1 font-bold text-white shadow-sm">
                                        {selectedPlan?.currency || 'BDT'} {(Number(contractedFlats || 0) * Number(selectedPlan?.price || 50)).toFixed(2)} / month
                                    </span>
                                </div>
                                <span className="text-xs text-slate-400">
                                    Monthly Subscription Amount is automatically kept in sync
                                </span>
                            </div>

                            {/* Building & Flat fields */}
                            <div className="grid gap-4 md:grid-cols-3">
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-slate-700">Contracted Flats *</label>
                                    <input
                                        type="number"
                                        min="1"
                                        max="5000"
                                        name="contracted_flats"
                                        value={contractedFlats}
                                        onChange={(e) => handleContractedFlatsChange(e.target.value)}
                                        className={inputTokenClass}
                                        required
                                    />
                                    <p className="mt-1 text-[11px] text-slate-500">Changing this automatically updates the Subscription Amount above.</p>
                                    {errors?.contracted_flats && <p className="mt-1 text-xs text-rose-600">{errors.contracted_flats}</p>}
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-medium text-slate-700">Total Floors *</label>
                                    <input
                                        type="number"
                                        min="1"
                                        max="200"
                                        name="total_floors"
                                        value={totalFloors}
                                        onChange={(e) => setTotalFloors(e.target.value)}
                                        className={inputTokenClass}
                                        required
                                    />
                                    <p className="mt-1 text-[11px] text-slate-500">
                                        Avg ~{Math.ceil((Number(contractedFlats) || 0) / Math.max(1, Number(totalFloors) || 1))} flats per floor
                                    </p>
                                    {errors?.total_floors && <p className="mt-1 text-xs text-rose-600">{errors.total_floors}</p>}
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-medium text-slate-700">Building Name *</label>
                                    <input
                                        type="text"
                                        name="building_name"
                                        value={buildingName}
                                        onChange={(e) => setBuildingName(e.target.value)}
                                        className={inputTokenClass}
                                        placeholder="e.g. Gulshan Tower"
                                        required
                                    />
                                    {errors?.building_name && <p className="mt-1 text-xs text-rose-600">{errors.building_name}</p>}
                                </div>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label className="mb-1 block text-xs font-medium text-slate-700">Building Address</label>
                                    <input
                                        type="text"
                                        name="building_address"
                                        value={buildingAddress}
                                        onChange={(e) => setBuildingAddress(e.target.value)}
                                        className={inputTokenClass}
                                        placeholder="e.g. Plot 15, Road 27, Gulshan-1, Dhaka"
                                    />
                                    {errors?.building_address && <p className="mt-1 text-xs text-rose-600">{errors.building_address}</p>}
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-medium text-slate-700">Remote Install URL</label>
                                    <input
                                        type="text"
                                        name="install_url"
                                        value={installUrl}
                                        onChange={(e) => setInstallUrl(e.target.value)}
                                        className={inputTokenClass}
                                        placeholder="https://app.mybuilding.com"
                                    />
                                    {errors?.install_url && <p className="mt-1 text-xs text-rose-600">{errors.install_url}</p>}
                                </div>
                            </div>

                            {/* Remote Provisioning Status Card */}
                            <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-xs">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs font-bold uppercase tracking-wider text-slate-500">Provisioning Status:</span>
                                        {provision ? (
                                            <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-bold capitalize ${
                                                provision.status === 'provisioned'
                                                    ? 'bg-emerald-100 text-emerald-800 border-emerald-300'
                                                    : provision.status === 'failed'
                                                        ? 'bg-rose-100 text-rose-800 border-rose-300'
                                                        : 'bg-amber-100 text-amber-800 border-amber-300'
                                            }`}>
                                                {provision.status}
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                                Will be configured on save
                                            </span>
                                        )}
                                    </div>

                                    {provision?.routes?.provision && (
                                        <button
                                            type="button"
                                            disabled={isProvisioning}
                                            onClick={() => {
                                                router.post(provision.routes.provision, {}, {
                                                    preserveScroll: true,
                                                    onStart: () => setIsProvisioning(true),
                                                    onFinish: () => setIsProvisioning(false),
                                                });
                                            }}
                                            className="inline-flex items-center gap-1.5 rounded-full bg-slate-900 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-50 transition-colors"
                                        >
                                            {isProvisioning ? (
                                                <>
                                                    <svg className="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                                    </svg>
                                                    <span>Provisioning…</span>
                                                </>
                                            ) : (
                                                <>
                                                    <svg className="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                                    </svg>
                                                    <span>{provision.status === 'provisioned' ? 'Re-provision Building' : 'Provision Building Now'}</span>
                                                </>
                                            )}
                                        </button>
                                    )}
                                </div>

                                {provision && (
                                    <div className="mt-3 grid gap-3 border-t border-slate-100 pt-3 text-xs sm:grid-cols-3">
                                        <div>
                                            <span className="text-slate-400 block">Remote Building ID</span>
                                            <span className="font-mono font-medium text-slate-800">
                                                {provision.remote_building_id ? `#${provision.remote_building_id}` : 'Not provisioned yet'}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="text-slate-400 block">Registration Code</span>
                                            <span className="font-mono font-medium text-slate-800">
                                                {provision.registration_code || '—'}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="text-slate-400 block">Last Provisioned</span>
                                            <span className="text-slate-800">
                                                {provision.provisioned_at || '—'}
                                            </span>
                                        </div>
                                    </div>
                                )}

                                {provision?.last_error && (
                                    <div className="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-2.5 text-xs text-rose-700">
                                        <strong>Error:</strong> {provision.last_error}
                                    </div>
                                )}

                                {!secret_configured && (
                                    <div className="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-2.5 text-xs text-amber-800">
                                        <strong>Notice:</strong> <code>MYBUILDING_PROVISION_SECRET</code> is not set in <code>.env</code>. Remote hand-off will be queued until the secret is configured.
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {is_edit && licenseManager?.licenses?.length > 0 && (
                        <div className="space-y-3">
                            <label className="block text-sm font-medium text-slate-700">Licenses</label>
                            {licenseManager.licenses.map((license) => (
                                <LicenseCard key={license.id} license={license} csrf={csrf} statusClass={licenseStatusClass} moveTargets={licenseManager?.move_targets || []} />
                            ))}
                        </div>
                    )}

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Notes</label>
                        <textarea name="notes" rows={1} defaultValue={fields?.notes || ''} className="w-full rounded-[10px] border border-slate-300 px-4 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-teal-600" />
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
                <div style={{ display: 'none' }}>
                    {licenseManager.licenses.map((license) => (
                        <React.Fragment key={license.id}>
                            {license.fields.status !== 'suspended' && license.fields.status !== 'revoked' && (
                                <form id={`suspend-form-${license.id}`} action={license.routes.suspend} method="POST" data-native="true">
                                    <input type="hidden" name="_token" value={csrf} />
                                    <input type="hidden" name="return_to_subscription" value="1" />
                                </form>
                            )}
                            {license.fields.status === 'suspended' && (
                                <form id={`unsuspend-form-${license.id}`} action={license.routes.unsuspend} method="POST" data-native="true">
                                    <input type="hidden" name="_token" value={csrf} />
                                    <input type="hidden" name="return_to_subscription" value="1" />
                                </form>
                            )}
                            {license.fields.status === 'revoked' && (
                                <form
                                    id={`reactivate-form-${license.id}`}
                                    action={license.routes.reactivate}
                                    method="POST"
                                    data-native="true"
                                    onSubmit={(e) => { if (!window.confirm('Reactivate this license?')) e.preventDefault(); }}
                                >
                                    <input type="hidden" name="_token" value={csrf} />
                                    <input type="hidden" name="return_to_subscription" value="1" />
                                </form>
                            )}
                            {license.fields.status !== 'revoked' && (
                                <form
                                    id={`terminate-form-${license.id}`}
                                    action={license.routes.terminate}
                                    method="POST"
                                    data-native="true"
                                    onSubmit={(e) => { if (!window.confirm('Terminate this license? The status will be set to revoked.')) e.preventDefault(); }}
                                >
                                    <input type="hidden" name="_token" value={csrf} />
                                    <input type="hidden" name="return_to_subscription" value="1" />
                                </form>
                            )}
                            <form
                                id={`reissue-key-form-${license.id}`}
                                action={license.routes.reissue_key}
                                method="POST"
                                data-native="true"
                            >
                                <input type="hidden" name="_token" value={csrf} />
                                <input type="hidden" name="return_to_subscription" value="1" />
                            </form>
                            <form
                                id={`certificate-issue-form-${license.id}`}
                                action={license.routes.certificate_issue}
                                method="POST"
                                data-native="true"
                            >
                                <input type="hidden" name="_token" value={csrf} />
                                <input type="hidden" name="return_to_subscription" value="1" />
                            </form>
                            {license.routes.certificate_revoke ? (
                                <form
                                    id={`certificate-revoke-form-${license.id}`}
                                    action={license.routes.certificate_revoke}
                                    method="POST"
                                    data-native="true"
                                >
                                    <input type="hidden" name="_token" value={csrf} />
                                    <input type="hidden" name="return_to_subscription" value="1" />
                                </form>
                            ) : null}
                            <form id={`edit-form-${license.id}`} action={license.form.action} method="POST" data-native="true">
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
                            </form>
                        </React.Fragment>
                    ))}
                </div>
            )}
        </>
    );
}
