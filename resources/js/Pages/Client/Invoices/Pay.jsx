import React, { useEffect, useMemo, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import SearchableSelect from '../../../Components/SearchableSelect';

const toHtmlWithLineBreaks = (value) => String(value || '').replace(/\n/g, '<br>');

export default function Pay({
    invoice = {},
    tax = {},
    company = {},
    gateways = [],
    payment_instructions = '',
    routes = {},
    payments = [],
    selected_gateway_id = null,
}) {
    const { errors = {}, flash = {} } = usePage().props;

    const errorMessages = Object.values(errors).filter(Boolean);
    const statusMessage = flash.status || '';
    const isStatusError = statusMessage && (
        statusMessage.toLowerCase().includes('fail') ||
        statusMessage.toLowerCase().includes('cancel') ||
        statusMessage.toLowerCase().includes('not be verified') ||
        statusMessage.toLowerCase().includes('unable') ||
        statusMessage.toLowerCase().includes('error')
    );

    const finalErrorMessage = flash.error || (isStatusError ? statusMessage : null) || (errorMessages.length > 0 ? errorMessages.join(', ') : null);
    const finalSuccessMessage = !isStatusError ? statusMessage : null;

    /* ── fire toast on mount ── */
    useEffect(() => {
        if (typeof window.showToast !== 'function') return;
        if (finalErrorMessage)   window.showToast(finalErrorMessage,   'error');
        if (finalSuccessMessage) window.showToast(finalSuccessMessage, 'success');
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const initialGatewayId = selected_gateway_id
        ? String(selected_gateway_id)
        : (gateways.length > 0 ? String(gateways[0].id) : '');
    const [gatewayId, setGatewayId] = useState(initialGatewayId);
    const gatewayOptions = gateways.map((gateway) => ({ value: String(gateway.id), label: gateway.name }));

    const selectedGateway = useMemo(
        () => gateways.find((gateway) => String(gateway.id) === String(gatewayId)) || null,
        [gateways, gatewayId],
    );

    const gatewayButtonLabel = (() => {
        if (!selectedGateway) {
            return 'Pay now';
        }

        const label = String(selectedGateway.button_label || '').trim();
        if (label !== '') {
            return label;
        }

        return `${selectedGateway.name} Pay`;
    })();

    const gatewayTarget = selectedGateway?.driver === 'bkash' && selectedGateway?.payment_url ? '_blank' : '_self';
    const showPaymentPanel = Boolean(invoice.is_payable);

    return (
        <>
            <Head title={`Invoice #${invoice.number_display || invoice.id || ''}`} />
            <style>{`
                * { box-sizing: border-box; }
                .invoice-container { width: 100%; background: #fff; padding: 10px; color: #333; font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; }
                .invoice-container .row { display: flex; flex-wrap: wrap; margin: 0 -15px; }
                .invoice-container .invoice-grid { display: table; width: 100%; table-layout: fixed; }
                .invoice-container .invoice-grid > .invoice-col { display: table-cell; width: 50%; vertical-align: top; }
                .invoice-container .invoice-col { width: 50%; padding: 0 15px; }
                .invoice-container .invoice-col.full { width: 100%; }
                .invoice-container .invoice-col.right { text-align: right; }
                .invoice-container .logo-wrap { display: flex; align-items: flex-start; }
                .invoice-container .invoice-logo-image {
                    display: block;
                    max-width: 340px;
                    max-height: 92px;
                    width: auto;
                    height: auto;
                    object-fit: contain;
                }
                .invoice-container .invoice-logo-fallback { font-size: 54px; font-weight: 800; color: #211f75; letter-spacing: -1px; line-height: 1; }
                .invoice-container .invoice-status { margin: 0; font-size: 24px; font-weight: bold; }
                .invoice-container .invoice-status h3 { margin: 0; font-size: 18px; font-weight: 600; }
                .invoice-container .small-text { font-size: 0.92em; }
                .invoice-container hr { margin: 20px 0; border: 0; border-top: 1px solid #eee; }
                .invoice-container address { margin: 8px 0 0; font-style: normal; line-height: 1.5; }
                .invoice-container .panel { margin-top: 14px; background: #fff; }
                .invoice-container .table-responsive { width: 100%; overflow-x: auto; }
                .invoice-container .table { width: 100%; max-width: 100%; margin-bottom: 20px; border-collapse: collapse; }
                .invoice-container .table > thead > tr > td,
                .invoice-container .table > tbody > tr > td { padding: 8px; line-height: 1.42857143; vertical-align: top; border: 1px solid #ddd; }
                .invoice-container .text-right { text-align: right !important; }
                .invoice-container .text-center { text-align: center !important; }
                .invoice-container .mt-5 { margin-top: 50px; }
                .invoice-container .mb-3 { margin-bottom: 30px; }
                .invoice-container .unpaid, .invoice-container .overdue { color: #cc0000; }
                .invoice-container .paid { color: #779500; }
                .invoice-container .refunded { color: #224488; }
                .invoice-container .cancelled { color: #888; }
                .invoice-container .text-muted { color: #666; }
                .payment-panel { border: 1px solid #ddd; padding: 12px; margin-top: 18px; }
                .payment-heading { font-weight: 700; margin-bottom: 8px; }
                .gateway-form .form-control { width: 100%; border: 1px solid #ccc; padding: 8px; margin-top: 6px; }
                .alert { padding: 8px 10px; margin-bottom: 10px; border: 1px solid transparent; }
                .alert.amber { border-color: #fcd34d; background: #fffbeb; color: #92400e; }
                .alert.rose { border-color: #fecdd3; background: #fff1f2; color: #9f1239; }
                @media (max-width: 767px) {
                    .invoice-container .invoice-col { padding: 0 10px; }
                    .invoice-container .invoice-logo-image { max-width: 240px; max-height: 72px; }
                }
                @media print {
                    .invoice-container .invoice-grid { display: table !important; width: 100% !important; table-layout: fixed !important; }
                    .invoice-container .invoice-grid > .invoice-col { display: table-cell !important; width: 50% !important; vertical-align: top !important; }
                    .no-print, .no-print * { display: none !important; }
                }
            `}</style>

            <div className="invoice-container">
                <div className="invoice-grid invoice-header">
                    <div className="invoice-col logo-wrap">
                        {company.logo_url ? (
                            <img src={company.logo_url} alt={`${company.name || 'Company'} logo`} className="invoice-logo-image" />
                        ) : (
                            <div className="invoice-logo-fallback">{String(company.name || '').toLowerCase()}</div>
                        )}
                    </div>
                    <div className="invoice-col text-right">
                        <div className="invoice-status">
                            <span className={invoice.status_class} style={{ textTransform: 'uppercase' }}>{invoice.status_label}</span>
                            <h3>Invoice #{invoice.number_display || invoice.id}</h3>
                            <div style={{ marginTop: 0, fontSize: 12 }}>
                                Invoice Date: <span className="small-text">{invoice.issue_date_display}</span>
                            </div>
                            <div style={{ marginTop: 0, fontSize: 12 }}>
                                Invoice Due Date: <span className="small-text">{invoice.due_date_display}</span>
                            </div>
                            {invoice.paid_at_display ? (
                                <div style={{ marginTop: 0, fontSize: 12 }}>
                                    Paid Date: <span className="small-text">{invoice.paid_at_display}</span>
                                </div>
                            ) : null}
                        </div>
                    </div>
                </div>

                <hr />

                {finalSuccessMessage && (
                    <div className="mb-6 p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center gap-3 shadow-sm no-print">
                        <svg className="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span className="font-semibold text-sm">{finalSuccessMessage}</span>
                    </div>
                )}

                {finalErrorMessage && (
                    <div className="mb-6 p-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 flex items-center gap-3 shadow-sm no-print">
                        <svg className="w-5 h-5 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span className="font-semibold text-sm">{finalErrorMessage}</span>
                    </div>
                )}

                <div className="invoice-grid invoice-addresses">
                    <div className="invoice-col" style={{ width: showPaymentPanel ? '33.33%' : '50%' }}>
                        <strong>Invoiced To</strong>
                        <address className="small-text">
                            {invoice?.customer?.name || '--'}
                            <br />
                            {invoice?.customer?.email || '--'}
                            <br />
                            {invoice?.customer?.address || '--'}
                        </address>
                    </div>
                    {showPaymentPanel ? (
                        <div className="invoice-col no-print" style={{ width: '33.33%', borderLeft: '1px solid #eee', borderRight: '1px solid #eee', paddingLeft: '20px', paddingRight: '20px' }}>
                            <div className="payment-heading" style={{ fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.5px', color: '#64748b', marginBottom: '8px', fontWeight: '700' }}>
                                Payment Method
                            </div>
                            {invoice.pending_proof ? <div className="alert amber" style={{ fontSize: '11px', padding: '6px', margin: '0 0 8px 0' }}>Pending review.</div> : null}
                            {!invoice.pending_proof && invoice.rejected_proof ? (
                                <div className="alert rose" style={{ fontSize: '11px', padding: '6px', margin: '0 0 8px 0' }}>Payment rejected.</div>
                            ) : null}

                            {gateways.length === 0 ? (
                                <div className="small-text text-muted" style={{ fontSize: '11px' }}>No active gateways.</div>
                            ) : (
                                <form method="POST" action={routes.checkout} id="gateway-form" className="gateway-form" target={gatewayTarget} data-native="true" style={{ margin: 0 }}>
                                    <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content || ''} />
                                    <SearchableSelect
                                        name="payment_gateway_id"
                                        value={gatewayId}
                                        onChange={(nextValue) => setGatewayId(String(nextValue || ''))}
                                        options={gatewayOptions}
                                        placeholder="Select gateway"
                                    />
                                    {selectedGateway?.instructions ? (
                                        <div
                                            id="gateway-instructions"
                                            className="small-text text-muted"
                                            style={{ marginTop: 6, marginBottom: 8, fontSize: '11px', lineHeight: '1.3' }}
                                            dangerouslySetInnerHTML={{
                                                __html: toHtmlWithLineBreaks(selectedGateway.instructions),
                                            }}
                                        />
                                    ) : null}
                                    <button 
                                        type="submit" 
                                        id="gateway-submit" 
                                        className="rounded bg-teal-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-teal-600 transition-colors"
                                        style={{ width: '100%', display: 'block', marginTop: '8px' }}
                                    >
                                        {gatewayButtonLabel}
                                    </button>
                                </form>
                            )}

                            {payment_instructions ? (
                                <div className="small-text text-muted whitespace-pre-line" style={{ marginTop: 8, fontSize: '11px', lineHeight: '1.3' }}>
                                    {payment_instructions}
                                </div>
                            ) : null}
                        </div>
                    ) : null}
                    <div className="invoice-col right" style={{ width: showPaymentPanel ? '33.33%' : '50%' }}>
                        <strong>Pay To</strong>
                        <address className="small-text">
                            {company.name}
                            <br />
                            {company.pay_to}
                            <br />
                            {company.email}
                        </address>
                    </div>
                </div>

                <div className="panel panel-default">
                    <div className="panel-body">
                        <div className="table-responsive">
                            <table className="table table-condensed">
                                <thead>
                                    <tr>
                                        <td>
                                            <strong>Description</strong>
                                        </td>
                                        <td width="20%" className="text-center">
                                            <strong>Amount</strong>
                                        </td>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(invoice.items || []).map((item) => (
                                        <tr key={item.id}>
                                            <td>{item.description}</td>
                                            <td className="text-center">{item.line_total_display}</td>
                                        </tr>
                                    ))}
                                    <tr>
                                        <td className="total-row text-right">
                                            <strong>Sub Total</strong>
                                        </td>
                                        <td className="total-row text-center">{invoice.subtotal_display}</td>
                                    </tr>
                                    {invoice.has_tax ? (
                                        <tr>
                                            <td className="total-row text-right">
                                                <strong>
                                                    {invoice.tax_mode === 'inclusive' ? 'Included Tax' : tax.label} ({invoice.tax_rate_percent_display}%)
                                                </strong>
                                            </td>
                                            <td className="total-row text-center">{invoice.tax_amount_display}</td>
                                        </tr>
                                    ) : null}
                                    <tr>
                                        <td className="total-row text-right">
                                            <strong>Discount</strong>
                                        </td>
                                        <td className="total-row text-center">- {invoice.discount_display}</td>
                                    </tr>
                                    <tr>
                                        <td className="total-row text-right">
                                            <strong>Payable Amount</strong>
                                        </td>
                                        <td className="total-row text-center">{invoice.payable_amount_display}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {payments && payments.length > 0 && (
                    <div className="panel panel-default" style={{ marginTop: '20px' }}>
                        <div className="panel-body">
                            <div className="payment-heading" style={{ fontSize: '15px', fontWeight: '700', marginBottom: '12px', color: '#1e293b' }}>
                                Payment Records
                            </div>
                            <div className="table-responsive">
                                <table className="table table-condensed" style={{ marginBottom: 0 }}>
                                    <thead style={{ background: '#f8fafc' }}>
                                        <tr>
                                            <td style={{ padding: '8px' }}><strong>Date</strong></td>
                                            <td style={{ padding: '8px' }}><strong>Payment Method</strong></td>
                                            <td style={{ padding: '8px' }}><strong>Reference</strong></td>
                                            <td className="text-center" style={{ padding: '8px' }}><strong>Amount</strong></td>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {payments.map((payment) => (
                                            <tr key={payment.id}>
                                                <td style={{ padding: '8px' }}>{payment.date_display}</td>
                                                <td style={{ padding: '8px' }}>{payment.method}</td>
                                                <td style={{ padding: '8px' }}>{payment.reference}</td>
                                                <td className="text-center font-semibold text-emerald-700" style={{ padding: '8px' }}>
                                                    {payment.amount_display}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                )}



                <div className="container-fluid invoice-container">
                    <div className="row mt-5" style={{ display: 'flex', justifyContent: 'center' }}>
                        <div className="invoice-col full no-print" style={{ textAlign: 'center' }}>
                            <div className="flex flex-wrap items-center justify-center gap-2">
                                <a href={routes.download} data-native="true" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                                    Download
                                </a>
                                <button
                                    type="button"
                                    onClick={() => window.print()}
                                    className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800"
                                >
                                    Print
                                </button>
                            </div>
                        </div>
                        <div className="invoice-col full" style={{ textAlign: 'center' }}>
                            <div className="mb-3">
                                <p>This is system generated invoice no signature required</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
