import React, { useEffect, useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Show({ pageTitle = 'Order', order = {}, plan_options = [], routes = {} }) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const flashStatus = props?.flash?.status || '';
    const csrf = props?.csrf_token || '';
    const canProcess = String(order?.status || '') === 'pending';
    const canUpdateBillingAmounts = ['pending', 'accepted'].includes(String(order?.status || ''));
    const statusKey = String(order?.status || '').toLowerCase();

    const statusClass =
        statusKey === 'accepted'
            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
            : statusKey === 'cancelled'
              ? 'border-rose-200 bg-rose-50 text-rose-700'
              : 'border-amber-200 bg-amber-50 text-amber-700';

    const initialPlanId = order?.plan_id != null ? String(order.plan_id) : '';
    const [selectedPlanId, setSelectedPlanId] = useState(initialPlanId);
    const [invoiceTotal, setInvoiceTotal] = useState(String(order?.invoice_total_value || ''));
    const [recurringAmount, setRecurringAmount] = useState(String(order?.recurring_amount_value || ''));
    const [hasPlanChanged, setHasPlanChanged] = useState(false);

    const formatIntervalLabel = (interval) => {
        const normalized = String(interval || '').trim().toLowerCase();
        return normalized ? normalized.charAt(0).toUpperCase() + normalized.slice(1) : '';
    };
    const formatPlanPriceLabel = (price, currency) => {
        const numericPrice = Number(price);
        const formattedPrice = Number.isFinite(numericPrice)
            ? numericPrice.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : '--';
        const currencyCode = String(currency || '').trim();
        return currencyCode ? `${currencyCode} ${formattedPrice}` : formattedPrice;
    };
    const toFixedMoney = (value) => {
        const numeric = Number(value);
        return Number.isFinite(numeric) ? numeric.toFixed(2) : '';
    };
    const calculateInvoiceTotalByInterval = (price, interval) => {
        const numericPrice = Number(price);
        if (!Number.isFinite(numericPrice)) return '';

        const normalized = String(interval || '').trim().toLowerCase();
        if (normalized === 'yearly') {
            return ((numericPrice / 365) * 360).toFixed(2);
        }

        return numericPrice.toFixed(2);
    };

    const selectedPlan = useMemo(
        () => plan_options.find((plan) => String(plan.id) === String(selectedPlanId)) || null,
        [plan_options, selectedPlanId]
    );

    const activeInterval = String(selectedPlan?.interval || order?.plan_interval || '').toLowerCase();
    const activeCurrency = String(selectedPlan?.currency || order?.invoice_currency || '').trim();
    const recurringAmountLabel = selectedPlan?.interval
        ? `Recurring Amount (${formatIntervalLabel(selectedPlan.interval)})`
        : 'Recurring Amount';

    const billingCycleDays = activeInterval === 'yearly' ? 360 : 30;

    const invoiceTotalBaseLabel = activeCurrency ? `Invoice Total (${activeCurrency})` : 'Invoice Total';
    const invoiceTotalLabel = `${invoiceTotalBaseLabel}: ${billingCycleDays} days`;

    useEffect(() => {
        if (!hasPlanChanged || !selectedPlan) return;
        const recurring = toFixedMoney(selectedPlan.price);
        const calculatedInvoiceTotal = calculateInvoiceTotalByInterval(selectedPlan.price, selectedPlan.interval);
        setInvoiceTotal(calculatedInvoiceTotal);
        setRecurringAmount(recurring);
    }, [hasPlanChanged, selectedPlan]);

    const topInvoiceAmountDisplay = useMemo(() => {
        if (invoiceTotal === '' || Number.isNaN(Number(invoiceTotal))) return '--';
        const formatted = Number(invoiceTotal).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return activeCurrency ? `${activeCurrency} ${formatted}` : formatted;
    }, [invoiceTotal, activeCurrency]);

    return (
        <>
            <Head title={pageTitle} />
            <div className="mx-auto max-w-7xl">
                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="bg-gradient-to-r from-teal-50 via-sky-50 to-white p-6 md:p-8">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div className="flex flex-wrap items-center gap-2">
                                <p className="text-xs uppercase tracking-[0.2em] text-slate-500">Order Detail</p>
                                <h3 className="mt-2 text-2xl font-semibold text-slate-900 md:text-3xl">#{order?.order_number || '--'}</h3>
                                <div className={`mt-3 inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold ${statusClass}`}>
                                    {order?.status_label || '--'}
                                </div>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                {order?.invoice_url ? (
                                    <a
                                        href={order.invoice_url}
                                        data-native="true"
                                        className="rounded-full border border-teal-200 bg-white px-4 py-2 text-sm font-semibold text-teal-700 transition hover:border-teal-300 hover:bg-teal-50"
                                    >
                                        Open Invoice
                                    </a>
                                ) : null}
                                <a
                                    href={routes?.index}
                                    data-native="true"
                                    className="rounded-full border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-400"
                                >
                                    Back to Orders
                                </a>
                            </div>
                        </div>
                    </div>
                    <div className="grid gap-3 p-6 text-sm md:grid-cols-2 xl:grid-cols-4">
                        <div className="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                            <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Customer</p>
                            <p className="mt-2 font-semibold text-slate-900">{order?.customer_name || '--'}</p>
                            <p className="mt-1 text-slate-600">{order?.customer_email || '--'}</p>
                        </div>
                        <div className="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                            <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Product</p>
                            <p className="mt-2 font-semibold text-slate-900">{order?.product_name || '--'}</p>
                            <p className="mt-1 text-slate-600">
                                Plan: {order?.plan_name || '--'}
                                {order?.plan_interval_label ? ` (${order.plan_interval_label})` : ''}
                            </p>
                        </div>
                        <div className="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                            <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Invoice</p>
                            <p className="mt-2 font-semibold text-slate-900">{order?.invoice_number || '--'}</p>
                            <p className="mt-1 text-slate-600">{topInvoiceAmountDisplay}</p>
                        </div>
                        <div className="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                            <p className="text-xs uppercase tracking-[0.18em] text-slate-500">Created</p>
                            <p className="mt-2 font-semibold text-slate-900">{order?.created_at_display || '--'}</p>
                        </div>
                    </div>

                    {flashStatus ? (
                        <div className="border-y border-emerald-200 bg-emerald-50 px-6 py-3 text-sm font-medium text-emerald-700">{flashStatus}</div>
                    ) : null}

                    <div className="grid items-start gap-6 p-6 xl:grid-cols-3">
                        <div className="grid items-start gap-6 md:grid-cols-2 xl:col-span-3">
                            <div className="rounded-3xl border border-slate-200 bg-white p-6">
                                <div className="mb-4">
                                    <h2 className="text-lg font-semibold text-slate-900">Update Plan & Interval</h2>
                                    <p className="mt-1 text-sm text-slate-500">Select one plan to update both plan and interval.</p>
                                </div>
                                <form action={routes?.update_plan} method="POST" data-native="true" className="space-y-4">
                                    <input type="hidden" name="_token" value={csrf} />
                                    <input type="hidden" name="_method" value="PATCH" />
                                    <div>
                                        <label className="mb-1 block text-sm font-medium text-slate-700">Plan & Interval</label>
                                        <select
                                            name="plan_id"
                                            value={selectedPlanId}
                                            onChange={(event) => {
                                                setSelectedPlanId(event.target.value);
                                                setHasPlanChanged(true);
                                            }}
                                            className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-slate-900 focus:border-teal-400 focus:outline-none"
                                        >
                                            <option value="" disabled>Select plan</option>
                                            {plan_options.map((plan) => (
                                                <option key={plan.id} value={plan.id}>
                                                    {plan.name} ({formatIntervalLabel(plan.interval)}) - {formatPlanPriceLabel(plan.price, plan.currency)}
                                                </option>
                                            ))}
                                        </select>
                                        {errors?.plan_id ? <p className="mt-1 text-xs text-rose-600">{errors.plan_id}</p> : null}
                                        {errors?.interval ? <p className="mt-1 text-xs text-rose-600">{errors.interval}</p> : null}
                                    </div>
                                    <div>
                                        <button
                                            type="submit"
                                            className="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
                                        >
                                            Update
                                        </button>
                                    </div>
                                </form>
                            </div>
                            {canUpdateBillingAmounts ? (
                                <div className="rounded-3xl border border-slate-200 bg-white p-6">
                                    <div className="mb-4">
                                        <h2 className="text-lg font-semibold text-slate-900">Update Billing Amounts</h2>
                                        <p className="mt-1 text-sm text-slate-500">Update invoice total and recurring amount from this section.</p>
                                     </div>
                                    <form action={routes?.update_amounts} method="POST" data-native="true" className="grid gap-4 md:grid-cols-2">
                                        <input type="hidden" name="_token" value={csrf} />
                                        <input type="hidden" name="_method" value="PATCH" />
                                        <div>
                                            <label className="mb-1 block text-sm font-medium text-slate-700">{invoiceTotalLabel}</label>
                                            <input
                                                name="invoice_total"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={invoiceTotal}
                                                onChange={(event) => setInvoiceTotal(event.target.value)}
                                                className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-slate-900 focus:border-teal-400 focus:outline-none"
                                            />
                                            {errors?.invoice_total ? <p className="mt-1 text-xs text-rose-600">{errors.invoice_total}</p> : null}
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-sm font-medium text-slate-700">{recurringAmountLabel}</label>
                                            <input
                                                name="recurring_amount"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                value={recurringAmount}
                                                onChange={(event) => setRecurringAmount(event.target.value)}
                                                className="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-slate-900 focus:border-teal-400 focus:outline-none"
                                            />
                                            {errors?.recurring_amount ? <p className="mt-1 text-xs text-rose-600">{errors.recurring_amount}</p> : null}
                                        </div>
                                        <div className="md:col-span-2">
                                            <button
                                                type="submit"
                                                className="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
                                            >
                                                Save Amounts
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            ) : (
                                <div className="rounded-3xl border border-slate-200 bg-slate-50 p-6">
                                    <h2 className="text-lg font-semibold text-slate-900">Billing Amounts</h2>
                                    <p className="mt-2 text-sm text-slate-600">Billing amounts are locked for this order status.</p>
                                </div>
                            )}
                            
                        </div>

                        {canProcess ? (
                            <div className="rounded-3xl border border-emerald-200 bg-emerald-50/60 p-6 xl:col-span-3">
                                <h2 className="mb-4 text-lg font-semibold text-emerald-800">Approve Order</h2>
                                <form id="approve-order-form" action={routes?.approve} method="POST" data-native="true" className="space-y-4">
                                    <input type="hidden" name="_token" value={csrf} />
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div>
                                            <label className="mb-1 block text-sm font-medium text-emerald-900">License Key</label>
                                            <input
                                                name="license_key"
                                                defaultValue={order?.license_key || ''}
                                                className="w-full rounded-xl border border-emerald-200 bg-white px-3 py-2.5 text-slate-900 focus:border-emerald-400 focus:outline-none"
                                            />
                                            {errors?.license_key ? <p className="mt-1 text-xs text-rose-600">{errors.license_key}</p> : null}
                                        </div>
                                        <div>
                                            <label className="mb-1 block text-sm font-medium text-emerald-900">License URL / Domain</label>
                                            <input
                                                name="license_url"
                                                defaultValue={order?.license_url || ''}
                                                className="w-full rounded-xl border border-emerald-200 bg-white px-3 py-2.5 text-slate-900 focus:border-emerald-400 focus:outline-none"
                                            />
                                            {errors?.license_url ? <p className="mt-1 text-xs text-rose-600">{errors.license_url}</p> : null}
                                        </div>
                                    </div>
                                </form>
                                <div className="mt-4 flex flex-wrap items-center gap-3">
                                    <button
                                        type="submit"
                                        form="approve-order-form"
                                        className="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500"
                                    >
                                        Approve Order
                                    </button>
                                    <form action={routes?.cancel} method="POST" data-native="true" className="inline-flex">
                                        <input type="hidden" name="_token" value={csrf} />
                                        <button
                                            type="submit"
                                            className="rounded-xl border border-rose-300 bg-white px-4 py-2.5 text-sm font-semibold text-rose-700 transition hover:bg-rose-100"
                                        >
                                            Cancel Order
                                        </button>
                                    </form>
                                    <form action={routes?.destroy} method="POST" data-native="true" className="inline-flex">
                                        <input type="hidden" name="_token" value={csrf} />
                                        <input type="hidden" name="_method" value="DELETE" />
                                        <button
                                            type="submit"
                                            className="rounded-xl border border-rose-300 px-4 py-2.5 text-sm font-semibold text-rose-700 transition hover:bg-rose-50"
                                        >
                                            Delete Order
                                        </button>
                                    </form>
                                </div>
                            </div>
                        ) : null}
                    </div>
            </div>
            </div>
        </>
    );
}
