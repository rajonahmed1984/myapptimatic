import React from 'react';
import { Head, usePage } from '@inertiajs/react';

const subscriptionStatusClass = (status) => {
    if (status === 'active') return 'bg-emerald-100 text-emerald-700 border-emerald-200';
    if (status === 'cancelled') return 'bg-rose-100 text-rose-700 border-rose-200';
    if (status === 'suspended') return 'bg-amber-100 text-amber-700 border-amber-200';
    return 'bg-slate-100 text-slate-600 border-slate-200';
};

const invoiceStatusClass = (status) => {
    if (status === 'paid') return 'bg-emerald-100 text-emerald-700 border-emerald-200';
    if (status === 'overdue') return 'bg-rose-100 text-rose-700 border-rose-200';
    if (status === 'cancelled' || status === 'refunded') return 'bg-slate-100 text-slate-600 border-slate-200';
    return 'bg-amber-100 text-amber-700 border-amber-200';
};

const Info = ({ label, value }) => (
    <div>
        <div className="text-xs uppercase tracking-[0.2em] text-slate-500">{label}</div>
        <div className="mt-1 text-sm text-slate-800">{value || '--'}</div>
    </div>
);

export default function Show({
    pageTitle = 'Subscription Details',
    subscription = {},
    invoices = [],
    routes = {},
}) {
    const { props } = usePage();
    const csrf = props?.csrf_token || '';

    return (
        <>
            <Head title={pageTitle} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div className="text-2xl font-semibold text-slate-900">Subscription #{subscription?.id || '--'}</div>
                    <div className="mt-1 text-sm text-slate-500">
                        {subscription?.customer_url ? (
                            <a href={subscription.customer_url} data-native="true" className="text-teal-600 hover:text-teal-500">
                                {subscription.customer_name}
                            </a>
                        ) : (
                            subscription?.customer_name || '--'
                        )}
                    </div>
                </div>
                <div className="flex items-center gap-3">
                    <a href={routes?.index} data-native="true" className="rounded-full border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">
                        Back to Subscriptions
                    </a>
                    <a href={routes?.edit} data-native="true" className="rounded-full bg-slate-900 px-4 py-2 text-sm font-semibold text-white">
                        Edit Subscription
                    </a>
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-4">
                <div className="card p-4">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-500">Status</div>
                    <div className="mt-2">
                        <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${subscriptionStatusClass(subscription?.status)}`}>
                            {subscription?.status_label || '--'}
                        </span>
                    </div>
                </div>
                <div className="card p-4">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-500">Amount</div>
                    <div className="mt-2 text-xl font-semibold text-slate-900">{subscription?.amount_display || '--'}</div>
                </div>
                <div className="card p-4">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-500">Next Invoice</div>
                    <div className="mt-2 text-xl font-semibold text-slate-900">{subscription?.next_invoice_at || '--'}</div>
                    {Number(subscription?.open_invoices_count || 0) > 0 ? (
                        <div className="mt-2 text-xs text-amber-700">
                            Stacked open invoices: {subscription.open_invoices_count}
                            {Number(subscription?.overdue_invoices_count || 0) > 0
                                ? ` (Overdue ${subscription.overdue_invoices_count})`
                                : ''}
                        </div>
                    ) : null}
                </div>
                <div className="card p-4">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-500">Invoices</div>
                    <div className="mt-2 text-xl font-semibold text-slate-900">{invoices.length}</div>
                </div>
            </div>

            <div className="mt-6 card p-6">
                <div className="mb-4 text-sm font-semibold text-slate-800">Subscription Details</div>
                <div className="grid gap-4 md:grid-cols-4">
                    <Info label="Product" value={subscription?.product_name} />
                    <Info label="Plan" value={subscription?.plan_name} />
                    <Info label="Interval" value={subscription?.plan_interval} />
                    <Info label="Created At" value={subscription?.created_at} />
                    <Info label="Start Date" value={subscription?.start_date} />
                    <Info label="Current Period Start" value={subscription?.current_period_start} />
                    <Info label="Current Period End" value={subscription?.current_period_end} />
                    <Info label="Next Invoice Date" value={subscription?.next_invoice_at} />
                    <Info label="Stacked Open Invoices" value={subscription?.open_invoices_count} />
                    <Info label="Overdue Invoices" value={subscription?.overdue_invoices_count} />
                    <Info label="Sales Rep" value={subscription?.sales_rep_name} />
                    <Info label="Sales Rep Status" value={subscription?.sales_rep_status} />
                    <Info label="Commission (%)" value={subscription?.sales_rep_commission_percent} />
                    <Info label="Commission Amount" value={subscription?.sales_rep_commission_amount_display} />
                    <Info label="Auto Renew" value={subscription?.auto_renew ? 'Yes' : 'No'} />
                    <Info label="Cancel At Period End" value={subscription?.cancel_at_period_end ? 'Yes' : 'No'} />
                </div>
                <div className="mt-4">
                    <div className="text-xs uppercase tracking-[0.2em] text-slate-500">Notes</div>
                    <div className="mt-1 text-sm text-slate-700">{subscription?.notes || '--'}</div>
                </div>
            </div>

            <div className="mt-6 card overflow-x-auto">
                <div className="border-b border-slate-200 px-4 py-3">
                    <div className="text-sm font-semibold text-slate-800">Invoice Records</div>
                </div>
                <table className="w-full min-w-[980px] text-left text-sm">
                    <thead className="border-b border-slate-300 text-xs uppercase tracking-[0.25em] text-slate-500">
                        <tr>
                            <th className="px-4 py-3">ID</th>
                            <th className="px-4 py-3">Issue Date</th>
                            <th className="px-4 py-3">Due Date</th>
                            <th className="px-4 py-3">Paid Date</th>
                            <th className="px-4 py-3">Status</th>
                            <th className="px-4 py-3">Total</th>
                            <th className="px-4 py-3">Commission</th>
                            <th className="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {invoices.length === 0 ? (
                            <tr>
                                <td colSpan={8} className="px-4 py-6 text-center text-slate-500">No invoice records found.</td>
                            </tr>
                        ) : (
                            invoices.map((invoice) => (
                                <tr key={invoice.id} className="border-b border-slate-100">
                                    <td className="px-4 py-3 text-slate-500">
                                        <a href={invoice.show_url} data-native="true" className="text-teal-600 hover:text-teal-500">
                                            {invoice.id}
                                        </a>
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">{invoice.issue_date}</td>
                                    <td className="px-4 py-3 text-slate-600">{invoice.due_date}</td>
                                    <td className="px-4 py-3 text-slate-600">{invoice.paid_date}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full border px-2.5 py-0.5 text-xs font-semibold ${invoiceStatusClass(invoice.status)}`}>
                                            {invoice.status_label}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 font-semibold text-slate-800">{invoice.total_display}</td>
                                    <td className="px-4 py-3 font-semibold text-slate-700">{invoice.commission_display || '--'}</td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="inline-flex items-center gap-2">
                                            {invoice.can_record_payment ? (
                                                <>
                                                    <a href={invoice.payment_url} data-native="true" className="text-emerald-600 hover:text-emerald-500">
                                                        Payment
                                                    </a>
                                                    <span className="text-slate-300">|</span>
                                                </>
                                            ) : null}
                                            <a href={invoice.show_url} data-native="true" className="text-teal-600 hover:text-teal-500">
                                                Invoice View
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
}
