import React, { useCallback, useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import DatePickerField from '../../../Components/DatePickerField';
import SearchableSelect from '../../../Components/SearchableSelect';

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
    const [selectedCustomerOption, setSelectedCustomerOption] = useState(() => {
        const selected = customers.find((customer) => String(customer.id) === selectedCustomerId);
        return selected ? { value: String(selected.id), label: selected.name } : null;
    });
    const [selectedInvoiceOption, setSelectedInvoiceOption] = useState(
        selectedInvoiceId ? invoicesById[selectedInvoiceId] || selectedInvoicePrefill : null,
    );
    const typeOptions = types.map((type) => ({ value: String(type), label: type }));
    const customerOptions = useMemo(() => {
        const initial = customers.map((customer) => ({ value: String(customer.id), label: customer.name }));
        const selectedExists = selectedCustomerOption
            && initial.some((option) => option.value === selectedCustomerOption.value);

        return [
            { value: '', label: 'Select customer' },
            ...initial,
            ...(selectedCustomerOption && !selectedExists ? [selectedCustomerOption] : []),
        ];
    }, [customers, selectedCustomerOption]);
    const invoiceOptions = useMemo(() => {
        const initial = invoices.map((invoice) => ({
            ...invoice,
            value: String(invoice.id),
            label: `${invoice.label} - ${invoice.customer_name}`,
        }));
        const selected = selectedInvoiceOption
            ? {
                ...selectedInvoiceOption,
                value: String(selectedInvoiceOption.value || selectedInvoiceOption.id || ''),
                label: String(
                    selectedInvoiceOption.value
                        ? selectedInvoiceOption.label
                        : `${selectedInvoiceOption.label} - ${selectedInvoiceOption.customer_name}`,
                ),
            }
            : null;
        const selectedExists = selected && initial.some((option) => option.value === selected.value);

        return [
            { value: '', label: 'Select invoice' },
            ...initial,
            ...(selected && !selectedExists ? [selected] : []),
        ];
    }, [invoices, selectedInvoiceOption]);
    const gatewayOptions = [
        { value: '', label: 'Select gateway' },
        ...gateways.map((gateway) => ({ value: String(gateway.id), label: gateway.name })),
    ];

    const activeInvoice = selectedInvoiceId
        ? selectedInvoiceOption || invoicesById[selectedInvoiceId] || null
        : null;
    const invoiceSummary = selectedInvoiceId ? activeInvoice || selectedInvoicePrefill : null;

    const loadLookupOptions = useCallback(async (url, search) => {
        if (!url) {
            return [];
        }

        const endpoint = new URL(url, window.location.origin);
        if (search) {
            endpoint.searchParams.set('q', search);
        }

        const response = await fetch(endpoint.toString(), {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(`Lookup failed with status ${response.status}`);
        }

        const payload = await response.json();
        return Array.isArray(payload?.data) ? payload.data : [];
    }, []);

    const loadCustomerOptions = useCallback(
        (search) => loadLookupOptions(routes?.customer_options, search),
        [loadLookupOptions, routes?.customer_options],
    );
    const loadInvoiceOptions = useCallback(
        (search) => loadLookupOptions(routes?.invoice_options, search),
        [loadLookupOptions, routes?.invoice_options],
    );

    const handleInvoiceChange = (nextValue, option = null) => {
        const nextInvoiceId = String(nextValue || '');
        setSelectedInvoiceId(nextInvoiceId);

        const invoice = nextInvoiceId ? option || invoicesById[nextInvoiceId] || null : null;
        setSelectedInvoiceOption(invoice);
        if (!invoice) {
            return;
        }

        setSelectedCustomerId(String(invoice.customer_id || ''));
        setSelectedCustomerOption({
            value: String(invoice.customer_id || ''),
            label: String(invoice.customer_name || invoice.customer_id || ''),
        });

        if (selectedType === 'payment') {
            const invoiceLabel = String(invoice.invoice_label || invoice.label || invoice.id || '');
            setAmountValue(Number(invoice.due_amount || 0).toFixed(2));
            setReferenceValue(invoiceLabel);
            setDescriptionValue(`Payment for Invoice #${invoiceLabel}`);
        }
    };

    return (
        <>
            <Head title={pageTitle} />
            <div className="mx-auto max-w-4xl space-y-4">
                {invoiceSummary ? (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4">
                        <div className="text-xs uppercase tracking-[0.2em] text-emerald-700">Selected Invoice</div>
                        <div className="mt-2 grid gap-3 text-sm md:grid-cols-3">
                            <div>
                                <div className="text-xs text-slate-500">Invoice</div>
                                <div className="font-semibold text-slate-900">#{invoiceSummary.invoice_label || invoiceSummary.label || invoiceSummary.id}</div>
                            </div>
                            <div>
                                <div className="text-xs text-slate-500">Customer</div>
                                <div className="font-semibold text-slate-900">{invoiceSummary.customer_name || '--'}</div>
                                {invoiceSummary.customer_email ? <div className="text-xs text-slate-500">{invoiceSummary.customer_email}</div> : null}
                            </div>
                            <div>
                                <div className="text-xs text-slate-500">Status / Due</div>
                                <div className="font-semibold text-slate-900">{String(invoiceSummary.status || '--').toUpperCase()}</div>
                                <div className="whitespace-nowrap text-xs text-slate-500">Due: {invoiceSummary.due_date || '--'}</div>
                            </div>
                            <div>
                                <div className="text-xs text-slate-500">Invoice Date</div>
                                <div className="whitespace-nowrap font-semibold text-slate-900">{invoiceSummary.issue_date || '--'}</div>
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

                <div className="rounded-2xl border border-slate-200 bg-white p-6">
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
                                <SearchableSelect
                                    name="type"
                                    value={selectedType}
                                    onChange={(nextValue) => setSelectedType(String(nextValue || 'payment'))}
                                    options={typeOptions}
                                    placeholder="Select type"
                                />
                            </div>
                            <div>
                                <DatePickerField
                                    name="entry_date"
                                    defaultValue={fields?.entry_date || ''}
                                    submitFormat="iso"
                                    label="Entry Date"
                                    labelClassName="mb-1 block text-sm font-medium text-slate-700"
                                    inputClassName="ui-input"
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
                                    className="ui-input"
                                />
                                {errors?.amount ? <p className="mt-1 text-xs text-rose-600">{errors.amount}</p> : null}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Currency</label>
                                <input name="currency" defaultValue={fields?.currency || ''} className="ui-input" />
                                {errors?.currency ? <p className="mt-1 text-xs text-rose-600">{errors.currency}</p> : null}
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Customer</label>
                                <SearchableSelect
                                    name="customer_id"
                                    value={selectedCustomerId}
                                    onChange={(nextValue, option) => {
                                        setSelectedCustomerId(String(nextValue || ''));
                                        setSelectedCustomerOption(option || null);
                                    }}
                                    options={customerOptions}
                                    loadOptions={loadCustomerOptions}
                                    placeholder="Select customer"
                                    error={errors?.customer_id}
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Invoice</label>
                                <SearchableSelect
                                    name="invoice_id"
                                    value={selectedInvoiceId}
                                    onChange={handleInvoiceChange}
                                    options={invoiceOptions}
                                    loadOptions={loadInvoiceOptions}
                                    placeholder="Select invoice"
                                    error={errors?.invoice_id}
                                />
                            </div>
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-slate-700">Payment Gateway</label>
                            <SearchableSelect
                                name="payment_gateway_id"
                                defaultValue={String(fields?.payment_gateway_id || '')}
                                options={gatewayOptions}
                                placeholder="Select gateway"
                            />
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Reference</label>
                                <input name="reference" value={referenceValue} onChange={(event) => setReferenceValue(event.target.value)} className="ui-input" />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700">Description</label>
                                <input name="description" value={descriptionValue} onChange={(event) => setDescriptionValue(event.target.value)} className="ui-input" />
                            </div>
                        </div>

                        {form?.due_amount !== null && form?.due_amount !== undefined ? (
                            <p className="text-sm text-slate-500">Invoice due amount: {Number((invoiceSummary?.due_amount ?? form.due_amount) || 0).toFixed(2)}</p>
                        ) : null}

                        <button type="submit" className="ui-btn-primary">
                            {is_edit ? 'Update Entry' : 'Create Entry'}
                        </button>
                    </form>
                </div>
            </div>
        </>
    );
}
