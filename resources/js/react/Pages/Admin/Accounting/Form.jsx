import React, { useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';

export default function Form({ pageTitle = 'Accounting Entry', is_edit = false, form = {}, types = [], customers = [], invoices = [], gateways = [], routes = {} }) {
    const { props } = usePage();
    const errors = props?.errors || {};
    const csrf = props?.csrf_token || '';
    const fields = form?.fields || {};
    const selectedInvoicePrefill = form?.selected_invoice || null;

    const invoicesById = useMemo(() => {
        return (Array.isArray(invoices) ? invoices : []).reduce((carry, invoice) => {
            carry[String(invoice.id)] = invoice;
            return carry;
        }, {});
    }, [invoices]);

    const [selectedType, setSelectedType] = useState(String(fields?.type || 'payment'));
    const [selectedInvoiceId, setSelectedInvoiceId] = useState(String(fields?.invoice_id || ''));
    const [selectedCustomerId, setSelectedCustomerId] = useState(String(fields?.customer_id || ''));
    const [amountValue, setAmountValue] = useState(String(fields?.amount || ''));
    const [referenceValue, setReferenceValue] = useState(String(fields?.reference || ''));
    const [descriptionValue, setDescriptionValue] = useState(String(fields?.description || ''));

    const activeInvoice = selectedInvoiceId ? invoicesById[selectedInvoiceId] || null : null;
    const invoiceSummary = activeInvoice || selectedInvoicePrefill;

    const handleInvoiceChange = (event) => {
        const nextInvoiceId = String(event.target.value || '');
        setSelectedInvoiceId(nextInvoiceId);

        const invoice = invoicesById[nextInvoiceId];
        if (!invoice) {
            return;
        }

        setSelectedCustomerId(String(invoice.customer_id || ''));

        if (selectedType === 'payment') {
            setAmountValue(Number(invoice.due_amount || 0).toFixed(2));
            setReferenceValue(String(invoice.label || invoice.id || ''));
            setDescriptionValue(`Payment for Invoice #${String(invoice.label || invoice.id || '')}`);
        }
    };

    return (
        <>
            <Head title={pageTitle} />
            <div className="mx-auto max-w-4xl rounded-2xl border border-slate-200 bg-white p-6">
                <div className="mb-6 flex items-center justify-between">
                    <h1 className="text-xl font-semibold text-slate-900">{pageTitle}</h1>
                    <a href={routes?.index} data-native="true" className="text-sm font-medium text-teal-600 hover:text-teal-500">
                        Back
                    </a>
                </div>

                <form action={form?.action} method="POST" data-native="true" className="space-y-4">
                    <input type="hidden" name="_token" value={csrf} />
                    {String(form?.method || 'POST').toUpperCase() !== 'POST' ? (
                        <input type="hidden" name="_method" value={form?.method} />
                    ) : null}

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Type</label>
                            <select
                                name="type"
                                value={selectedType}
                                onChange={(event) => setSelectedType(String(event.target.value || 'payment'))}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2"
                            >
                                {types.map((type) => (
                                    <option key={type} value={type}>
                                        {type}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Entry Date</label>
                            <input
                                type="date"
                                name="entry_date"
                                defaultValue={fields?.entry_date || ''}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2"
                            />
                            {errors?.entry_date ? <p className="mt-1 text-xs text-rose-600">{errors.entry_date}</p> : null}
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Amount</label>
                            <input
                                type="number"
                                min="0.01"
                                step="0.01"
                                name="amount"
                                value={amountValue}
                                onChange={(event) => setAmountValue(event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2"
                            />
                            {errors?.amount ? <p className="mt-1 text-xs text-rose-600">{errors.amount}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Currency</label>
                            <input name="currency" defaultValue={fields?.currency || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                            {errors?.currency ? <p className="mt-1 text-xs text-rose-600">{errors.currency}</p> : null}
                        </div>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Customer</label>
                            <select
                                name="customer_id"
                                value={selectedCustomerId}
                                onChange={(event) => setSelectedCustomerId(event.target.value)}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2"
                            >
                                <option value="">Select customer</option>
                                {customers.map((customer) => (
                                    <option key={customer.id} value={customer.id}>
                                        {customer.name}
                                    </option>
                                ))}
                            </select>
                            {errors?.customer_id ? <p className="mt-1 text-xs text-rose-600">{errors.customer_id}</p> : null}
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Invoice</label>
                            <select
                                name="invoice_id"
                                value={selectedInvoiceId}
                                onChange={handleInvoiceChange}
                                className="w-full rounded-lg border border-slate-300 px-3 py-2"
                            >
                                <option value="">Select invoice</option>
                                {invoices.map((invoice) => (
                                    <option key={invoice.id} value={invoice.id}>
                                        {invoice.label} - {invoice.customer_name}
                                    </option>
                                ))}
                            </select>
                            {errors?.invoice_id ? <p className="mt-1 text-xs text-rose-600">{errors.invoice_id}</p> : null}
                        </div>
                    </div>

                    {invoiceSummary ? (
                        <div className="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                            <div className="text-xs uppercase tracking-[0.2em] text-emerald-700">Selected Invoice</div>
                            <div className="mt-2 grid gap-3 md:grid-cols-3 text-sm">
                                <div>
                                    <div className="text-xs text-slate-500">Invoice</div>
                                    <div className="font-semibold text-slate-900">#{invoiceSummary.label || invoiceSummary.id}</div>
                                </div>
                                <div>
                                    <div className="text-xs text-slate-500">Customer</div>
                                    <div className="font-semibold text-slate-900">{invoiceSummary.customer_name || '--'}</div>
                                    {invoiceSummary.customer_email ? <div className="text-xs text-slate-500">{invoiceSummary.customer_email}</div> : null}
                                </div>
                                <div>
                                    <div className="text-xs text-slate-500">Status / Due</div>
                                    <div className="font-semibold text-slate-900">{String(invoiceSummary.status || '--').toUpperCase()}</div>
                                    <div className="text-xs text-slate-500 whitespace-nowrap">Due: {invoiceSummary.due_date || '--'}</div>
                                </div>
                                <div>
                                    <div className="text-xs text-slate-500">Invoice Date</div>
                                    <div className="font-semibold text-slate-900 whitespace-nowrap">{invoiceSummary.issue_date || '--'}</div>
                                </div>
                                <div>
                                    <div className="text-xs text-slate-500">Total Amount</div>
                                    <div className="font-semibold text-slate-900 tabular-nums">{Number(invoiceSummary.total_amount || 0).toFixed(2)}</div>
                                </div>
                                <div>
                                    <div className="text-xs text-slate-500">Due Amount</div>
                                    <div className="font-semibold text-emerald-700 tabular-nums">{Number(invoiceSummary.due_amount || 0).toFixed(2)}</div>
                                </div>
                            </div>
                        </div>
                    ) : null}

                    <div>
                        <label className="mb-1 block text-sm font-medium text-slate-700">Payment Gateway</label>
                        <select name="payment_gateway_id" defaultValue={fields?.payment_gateway_id || ''} className="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option value="">Select gateway</option>
                            {gateways.map((gateway) => (
                                <option key={gateway.id} value={gateway.id}>
                                    {gateway.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Reference</label>
                            <input name="reference" value={referenceValue} onChange={(event) => setReferenceValue(event.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                        </div>
                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Description</label>
                            <input name="description" value={descriptionValue} onChange={(event) => setDescriptionValue(event.target.value)} className="w-full rounded-lg border border-slate-300 px-3 py-2" />
                        </div>
                    </div>

                    {form?.due_amount !== null && form?.due_amount !== undefined ? (
                        <p className="text-sm text-slate-500">Invoice due amount: {Number((invoiceSummary?.due_amount ?? form.due_amount) || 0).toFixed(2)}</p>
                    ) : null}

                    <button type="submit" className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        {is_edit ? 'Update Entry' : 'Create Entry'}
                    </button>
                </form>
            </div>
        </>
    );
}
