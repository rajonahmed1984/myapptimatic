import React, { useEffect, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import SearchableSelect from '../../../Components/SearchableSelect';

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

const money = (value, currency) => {
    const amount = Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return currency ? `${currency} ${amount}` : amount;
};

const TIMING_OPTIONS = [
    {
        value: 'next_renewal',
        label: 'At next renewal',
        hint: 'Everything already invoiced stays. The new plan starts on the first day not yet billed.',
    },
    {
        value: 'now',
        label: 'Now, with credit',
        hint: 'The new term starts today and is invoiced now. Unused paid days are credited against it.',
    },
];

function ChangePlanCard({ plans, currentInterval, routes, csrf }) {
    const [planId, setPlanId] = useState('');
    const [timing, setTiming] = useState('next_renewal');
    const [amount, setAmount] = useState('');
    const [preview, setPreview] = useState(null);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const selectedPlan = plans.find((plan) => String(plan.id) === String(planId));
    const intervalChanges = selectedPlan && currentInterval && selectedPlan.interval !== currentInterval;

    useEffect(() => {
        if (!planId || !routes?.change_plan_preview) {
            setPreview(null);
            setError('');
            return undefined;
        }

        const controller = new AbortController();
        const timer = setTimeout(async () => {
            setLoading(true);
            setError('');
            try {
                const params = new URLSearchParams({ plan_id: planId, timing });
                if (amount !== '') params.set('subscription_amount', amount);
                const response = await fetch(`${routes.change_plan_preview}?${params.toString()}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal,
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    const firstError = payload?.errors ? Object.values(payload.errors)[0]?.[0] : null;
                    throw new Error(firstError || payload?.message || 'Could not work out the change.');
                }
                setPreview(payload?.data || null);
            } catch (exception) {
                if (exception?.name !== 'AbortError') {
                    setPreview(null);
                    setError(exception?.message || 'Could not work out the change.');
                }
            } finally {
                setLoading(false);
            }
        }, 250);

        return () => {
            controller.abort();
            clearTimeout(timer);
        };
    }, [planId, timing, amount, routes?.change_plan_preview]);

    const blocked = Boolean(preview?.blocked_reason);
    const currency = preview?.currency || '';

    return (
        <div className="card p-6">
            <div className="mb-2 text-sm font-semibold text-slate-800">Change Plan</div>
            <p className="mb-4 text-xs text-slate-500">
                Switch this subscription to another plan, including between monthly and yearly. The billing window and next invoice are re-fitted to the new plan.
            </p>
            <form action={routes?.change_plan} method="POST" data-native="true" className="space-y-3">
                <input type="hidden" name="_token" value={csrf} />
                <input type="hidden" name="_method" value="PUT" />
                <select
                    name="plan_id"
                    required
                    value={planId}
                    onChange={(event) => setPlanId(event.target.value)}
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="" disabled>Select new plan...</option>
                    {plans.map((plan) => (
                        <option key={plan.id} value={plan.id}>{plan.label || plan.name}</option>
                    ))}
                </select>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="subscription_amount"
                    value={amount}
                    onChange={(event) => setAmount(event.target.value)}
                    placeholder="Override amount (optional)"
                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                />

                <fieldset className="space-y-2">
                    <legend className="text-xs font-semibold text-slate-600">
                        When should it take effect?
                        {intervalChanges ? (
                            <span className="ml-1 font-normal text-slate-500">({currentInterval} → {selectedPlan.interval})</span>
                        ) : null}
                    </legend>
                    {TIMING_OPTIONS.map((option) => (
                        <label key={option.value} className="flex cursor-pointer items-start gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                            <input
                                type="radio"
                                name="timing"
                                value={option.value}
                                checked={timing === option.value}
                                onChange={() => setTiming(option.value)}
                                className="mt-1"
                            />
                            <span>
                                <span className="font-medium text-slate-800">{option.label}</span>
                                <span className="block text-xs text-slate-500">{option.hint}</span>
                            </span>
                        </label>
                    ))}
                </fieldset>

                {loading ? <div className="text-xs text-slate-500">Working out the change...</div> : null}
                {error ? <div className="rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700">{error}</div> : null}

                {preview && !loading ? (
                    <div className={`rounded-lg border px-3 py-3 text-xs ${blocked ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-slate-200 bg-slate-50 text-slate-700'}`}>
                        {blocked ? (
                            <div>{preview.blocked_reason}</div>
                        ) : (
                            <div className="space-y-1">
                                <div className="flex justify-between gap-3">
                                    <span>New amount</span>
                                    <span className="font-semibold">{money(preview.new_amount, currency)} / {preview.new_interval}</span>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <span>New term</span>
                                    <span className="font-semibold">{preview.term_start} → {preview.term_end}</span>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <span>First invoice</span>
                                    <span className="font-semibold">{money(preview.first_invoice_total, currency)}</span>
                                </div>
                                {preview.credit > 0 ? (
                                    <>
                                        <div className="flex justify-between gap-3 text-emerald-700">
                                            <span>Credit for unused paid time</span>
                                            <span className="font-semibold">- {money(preview.credit, currency)}</span>
                                        </div>
                                        <div className="flex justify-between gap-3 border-t border-slate-200 pt-1">
                                            <span>To pay now</span>
                                            <span className="font-semibold">{money(Math.max(0, preview.first_invoice_total - preview.credit), currency)}</span>
                                        </div>
                                    </>
                                ) : null}
                                <div className="flex justify-between gap-3">
                                    <span>{preview.timing === 'now' ? 'Invoice after that' : 'Next invoice'}</span>
                                    <span className="font-semibold">{preview.next_invoice_at}</span>
                                </div>
                                {(preview.notes || []).map((note) => (
                                    <div key={note} className="pt-1 text-slate-500">{note}</div>
                                ))}
                            </div>
                        )}
                    </div>
                ) : null}

                <button
                    type="submit"
                    disabled={!planId || blocked || loading}
                    className="rounded-full bg-slate-900 px-5 py-2 text-xs font-semibold text-white transition-colors hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                    onClick={(event) => {
                        const message = timing === 'now'
                            ? 'Change plan now? An invoice will be raised today.'
                            : 'Change plan from the next renewal?';
                        if (!confirm(message)) {
                            event.preventDefault();
                        }
                    }}
                >
                    Change Plan
                </button>
            </form>
        </div>
    );
}

export default function Show({
    pageTitle = 'Subscription Details',
    subscription = {},
    invoices = [],
    routes = {},
    customers = [],
    plans = [],
    related_counts = {},
    transfer_ineligible_reason = null,
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

            <div className="mt-6 grid gap-4 md:grid-cols-2">
                <div className="card p-6">
                    <div className="mb-2 text-sm font-semibold text-slate-800">Renew Now</div>
                    <p className="mb-4 text-xs text-slate-500">
                        Manually generate an invoice for this subscription's current period, outside the normal billing cycle.
                    </p>
                    <form action={routes?.renew_now} method="POST" data-native="true">
                        <input type="hidden" name="_token" value={csrf} />
                        <input type="hidden" name="_method" value="PUT" />
                        <button
                            type="submit"
                            className="rounded-full bg-slate-900 px-5 py-2 text-xs font-semibold text-white hover:bg-slate-800 transition-colors"
                            onClick={(e) => {
                                if (!confirm('Generate a new invoice for this subscription now?')) {
                                    e.preventDefault();
                                }
                            }}
                        >
                            Renew Now
                        </button>
                    </form>
                </div>

                <ChangePlanCard
                    plans={plans}
                    currentInterval={subscription?.plan_interval_value}
                    routes={routes}
                    csrf={csrf}
                />
            </div>

            <div className="mt-6 card p-6">
                <div className="mb-4 text-sm font-semibold text-slate-800">Transfer Subscription Owner</div>
                <form action={routes?.move_owner} method="POST" data-native="true" className="space-y-4">
                    <input type="hidden" name="_token" value={csrf} />
                    <input type="hidden" name="_method" value="PUT" />

                    <div className="grid gap-6 md:grid-cols-2">
                        <div>
                            <label className="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                                Select New Owner
                            </label>
                            <SearchableSelect
                                name="customer_id"
                                placeholder="Choose a client..."
                                options={customers.map(c => ({ value: String(c.id), label: c.name }))}
                                required
                            />
                            <p className="mt-2 text-xs text-slate-400">
                                Select the target client to transfer this subscription and its licenses to.
                            </p>
                        </div>

                        <div>
                            <label className="mb-2 block text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                                Transfer Associated Items
                            </label>
                            <div className="space-y-2 mt-2">
                                {related_counts?.projects > 0 ? (
                                    <label className="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                                        <input type="hidden" name="move_projects" value="0" />
                                        <input type="checkbox" name="move_projects" value="1" defaultChecked className="rounded border-slate-300 text-teal-600 focus:ring-teal-500" />
                                        <span>Move {related_counts.projects} related Project(s) (including maintenances and project invoices)</span>
                                    </label>
                                ) : null}

                                {related_counts?.orders > 0 ? (
                                    <label className="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                                        <input type="hidden" name="move_orders" value="0" />
                                        <input type="checkbox" name="move_orders" value="1" defaultChecked className="rounded border-slate-300 text-teal-600 focus:ring-teal-500" />
                                        <span>Move {related_counts.orders} related Order(s)</span>
                                    </label>
                                ) : null}

                                {related_counts?.invoices > 0 ? (
                                    <label className="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                                        <input type="hidden" name="move_invoices" value="0" />
                                        <input type="checkbox" name="move_invoices" value="1" defaultChecked className="rounded border-slate-300 text-teal-600 focus:ring-teal-500" />
                                        <span>Move {related_counts.invoices} related Invoice(s)</span>
                                    </label>
                                ) : null}

                                {(!related_counts?.projects && !related_counts?.orders && !related_counts?.invoices) ? (
                                    <p className="text-xs text-slate-400 italic">No other related items associated with this subscription.</p>
                                ) : null}
                            </div>
                        </div>
                    </div>

                    <div className="pt-2 border-t border-slate-100 flex justify-end">
                        <button
                            type="submit"
                            className="rounded-full bg-teal-600 px-5 py-2 text-xs font-semibold text-white hover:bg-teal-500 transition-colors"
                            onClick={(e) => {
                                if (!confirm("Are you sure you want to transfer this subscription to the selected client? This action is irreversible.")) {
                                    e.preventDefault();
                                }
                            }}
                        >
                            Transfer Owner
                        </button>
                    </div>
                </form>
            </div>

            <div className="mt-6 card p-6">
                <div className="mb-1 text-sm font-semibold text-slate-800">Request Ownership Transfer</div>
                <p className="mb-4 text-xs text-slate-500">
                    Sends an invite the receiving customer must accept before anything moves — unlike Transfer Owner above,
                    which moves it immediately. Moves this subscription and its licenses (and a linked project, if there's
                    exactly one) once accepted.
                </p>
                {transfer_ineligible_reason ? (
                    <p className="text-sm text-slate-500">{transfer_ineligible_reason}</p>
                ) : (
                    <form action={routes?.transfer_store} method="POST" data-native="true" className="space-y-3">
                        <input type="hidden" name="_token" value={csrf} />
                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                Receiving Customer
                            </label>
                            <SearchableSelect
                                name="to_customer_id"
                                placeholder="Choose a client..."
                                options={customers.map((c) => ({ value: String(c.id), label: c.name }))}
                                required
                            />
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                Schedule For (optional)
                            </label>
                            <input
                                type="datetime-local"
                                name="scheduled_for"
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                            />
                            <p className="mt-1 text-xs text-slate-400">Leave blank to execute as soon as the receiver accepts.</p>
                        </div>
                        <div>
                            <label className="mb-1 block text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                                Reason (optional)
                            </label>
                            <textarea name="reason" rows={2} className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        </div>
                        <button
                            type="submit"
                            className="rounded-full bg-slate-900 px-5 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                            onClick={(e) => {
                                if (!confirm('Send this ownership transfer invite? The receiving customer will need to accept it.')) {
                                    e.preventDefault();
                                }
                            }}
                        >
                            Send Transfer Invite
                        </button>
                    </form>
                )}
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
