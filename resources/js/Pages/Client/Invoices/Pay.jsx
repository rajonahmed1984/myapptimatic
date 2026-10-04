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

    const initialGatewayId = selected_gateway_id && gateways.some((gateway) => String(gateway.id) === String(selected_gateway_id))
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
                .invoice-container, .invoice-container * { box-sizing: border-box; }
                .invoice-container { width: 100%; background: #fff; padding: 10px; color: #333; font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; }

                /* header */
                .invoice-container .inv-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
                .invoice-container .invoice-logo-image { display: block; max-width: 340px; max-height: 92px; width: auto; height: auto; object-fit: contain; }
                .invoice-container .invoice-logo-fallback { font-size: 54px; font-weight: 800; color: #211f75; letter-spacing: -1px; line-height: 1; }
                .invoice-container .inv-meta { text-align: right; }
                .invoice-container .inv-status { display: inline-block; font-size: 24px; font-weight: bold; text-transform: uppercase; }
                .invoice-container .inv-number { margin: 0; font-size: 18px; font-weight: 600; }
                .invoice-container .inv-dates { font-size: 12px; }
                .invoice-container .inv-date-label { color: inherit; }

                .invoice-container hr { margin: 20px 0; border: 0; border-top: 1px solid #eee; }
                .invoice-container address { margin: 8px 0 0; font-style: normal; line-height: 1.5; overflow-wrap: anywhere; }
                .invoice-container .small-text { font-size: 0.92em; }
                .invoice-container .text-muted { color: #666; }
                .invoice-container .unpaid, .invoice-container .overdue { color: #cc0000; }
                .invoice-container .paid { color: #779500; }
                .invoice-container .refunded { color: #224488; }
                .invoice-container .cancelled { color: #888; }

                /* parties + payment card */
                .invoice-container .inv-parties { display: grid; grid-template-columns: 1fr 1fr; gap: 0; }
                .invoice-container .inv-parties.has-pay { grid-template-columns: 1fr minmax(220px, 1fr) 1fr; }
                .invoice-container .inv-party { padding: 0 15px; }
                .invoice-container .inv-party.right { text-align: right; }
                .invoice-container .inv-pay { padding: 0 20px; border-left: 1px solid #eee; border-right: 1px solid #eee; }
                .invoice-container .inv-pay-title { font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; font-weight: 700; margin-bottom: 4px; }
                .invoice-container .inv-pay-amount { font-size: 20px; font-weight: 800; color: #0f172a; line-height: 1.2; }
                .invoice-container .inv-pay-due { font-size: 11px; color: #64748b; margin-top: 2px; }
                .invoice-container .inv-pay-label { font-size: 12px; font-weight: 600; color: #334155; margin: 12px 0 6px; }
                .invoice-container .inv-instructions { margin-top: 8px; font-size: 11px; line-height: 1.4; color: #475569; background: #f8fafc; border-radius: 8px; padding: 8px 10px; }
                .invoice-container .inv-pay-btn { display: block; width: 100%; margin-top: 10px; border: 0; border-radius: 10px; background: #14b8a6; color: #fff; font-weight: 700; font-size: 13px; padding: 9px 12px; cursor: pointer; transition: background .15s; }
                .invoice-container .inv-pay-btn:hover { background: #0d9488; }
                .invoice-container .inv-pay-note { margin-top: 8px; font-size: 11px; line-height: 1.4; color: #64748b; white-space: pre-line; }
                .invoice-container .alert { padding: 6px 8px; margin: 8px 0 0; border: 1px solid transparent; border-radius: 8px; font-size: 11px; }
                .invoice-container .alert.amber { border-color: #fcd34d; background: #fffbeb; color: #92400e; }
                .invoice-container .alert.rose { border-color: #fecdd3; background: #fff1f2; color: #9f1239; }

                /* tables */
                .invoice-container .panel { margin-top: 14px; background: #fff; }
                .invoice-container .table-responsive { width: 100%; overflow-x: auto; }
                .invoice-container .table { width: 100%; max-width: 100%; margin-bottom: 20px; border-collapse: collapse; }
                .invoice-container .table > thead > tr > td,
                .invoice-container .table > tbody > tr > td { padding: 8px; line-height: 1.42857143; vertical-align: top; border: 1px solid #ddd; }
                .invoice-container .table .amount-col { width: 20%; text-align: center; white-space: nowrap; }
                .invoice-container .table .total-row.label { text-align: right; }
                .invoice-container .table tr.grand-total td { font-weight: 700; background: #f8fafc; }
                .invoice-container .records-title { font-size: 15px; font-weight: 700; margin-bottom: 12px; color: #1e293b; }

                /* footer */
                .invoice-container .inv-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; margin-top: 50px; }
                .invoice-container .inv-action { border-radius: 9999px; background: #0f172a; color: #fff; padding: 8px 16px; font-size: 12px; font-weight: 600; border: 0; cursor: pointer; text-align: center; text-decoration: none; }
                .invoice-container .inv-action:hover { background: #1e293b; }
                .invoice-container .inv-footnote { text-align: center; margin: 16px 0 30px; font-size: 13px; color: #64748b; }

                @media (max-width: 767px) {
                    .invoice-container { padding: 4px 2px; }
                    .invoice-container .inv-header { flex-direction: column; gap: 12px; }
                    .invoice-container .invoice-logo-image { max-width: 200px; max-height: 56px; }
                    .invoice-container .invoice-logo-fallback { font-size: 36px; }
                    .invoice-container .inv-meta { text-align: left; width: 100%; }
                    .invoice-container .inv-meta-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
                    .invoice-container .inv-status { font-size: 12px; padding: 4px 10px; border-radius: 9999px; background: #f1f5f9; letter-spacing: 0.5px; }
                    .invoice-container .inv-status.unpaid, .invoice-container .inv-status.overdue { background: #fef2f2; }
                    .invoice-container .inv-status.paid { background: #f7fee7; }
                    .invoice-container .inv-number { font-size: 20px; }
                    .invoice-container .inv-dates { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 10px; font-size: 13px; }
                    .invoice-container .inv-dates > div { background: #f8fafc; border-radius: 10px; padding: 8px 10px; }
                    .invoice-container .inv-date-label { display: block; font-size: 11px; color: #64748b; }
                    .invoice-container .inv-dates .small-text { font-size: 14px; font-weight: 600; color: #0f172a; }
                    .invoice-container hr { margin: 14px 0; }

                    .invoice-container .inv-parties,
                    .invoice-container .inv-parties.has-pay { grid-template-columns: 1fr 1fr; gap: 12px; }
                    .invoice-container .inv-party,
                    .invoice-container .inv-party.right { text-align: left; padding: 12px; border: 1px solid #eef2f7; border-radius: 12px; font-size: 14px; }
                    .invoice-container .inv-party address { font-size: 13px; }

                    .invoice-container .inv-pay { order: -1; grid-column: 1 / -1; border: 1px solid #99f6e4; border-radius: 14px; padding: 16px; background: #fbfffe; box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06); }
                    .invoice-container .inv-pay-title { font-size: 12px; }
                    .invoice-container .inv-pay-amount { font-size: 28px; }
                    .invoice-container .inv-pay-due { font-size: 13px; }
                    .invoice-container .inv-pay-label { font-size: 14px; margin-top: 16px; }
                    .invoice-container .inv-instructions { font-size: 13px; padding: 10px 12px; }
                    .invoice-container .inv-pay-btn { min-height: 50px; font-size: 16px; border-radius: 12px; margin-top: 14px; }
                    .invoice-container .inv-pay-note { font-size: 12px; }
                    .invoice-container .alert { font-size: 13px; padding: 8px 10px; }

                    .invoice-container .table { font-size: 14px; }
                    .invoice-container .table .amount-col { width: auto; text-align: right; }
                    .invoice-container .table tr.grand-total td { font-size: 16px; }

                    .invoice-container .records-table thead { display: none; }
                    .invoice-container .records-table, .invoice-container .records-table tbody,
                    .invoice-container .records-table tr, .invoice-container .records-table td { display: block; width: 100%; }
                    .invoice-container .records-table tr { border: 1px solid #e2e8f0; border-radius: 12px; padding: 6px 12px; margin-bottom: 8px; }
                    .invoice-container .records-table > tbody > tr > td { border: 0; padding: 4px 0; display: flex; justify-content: space-between; gap: 12px; text-align: right !important; }
                    .invoice-container .records-table > tbody > tr > td::before { content: attr(data-label); font-weight: 600; color: #64748b; text-align: left; }

                    .invoice-container .inv-actions { margin-top: 24px; }
                    .invoice-container .inv-action { flex: 1 1 0; min-height: 46px; font-size: 14px; display: inline-flex; align-items: center; justify-content: center; }
                }

                @media (max-width: 380px) {
                    .invoice-container .inv-parties,
                    .invoice-container .inv-parties.has-pay { grid-template-columns: 1fr; }
                }

                @media print {
                    .invoice-container .inv-header { flex-direction: row !important; }
                    .invoice-container .inv-meta { text-align: right !important; }
                    .invoice-container .inv-parties { grid-template-columns: 1fr 1fr !important; }
                    .no-print, .no-print * { display: none !important; }
                }
            `}</style>

            <div className="invoice-container">
                <div className="inv-header">
                    <div className="logo-wrap">
                        {company.logo_url ? (
                            <img src={company.logo_url} alt={`${company.name || 'Company'} logo`} className="invoice-logo-image" />
                        ) : (
                            <div className="invoice-logo-fallback">{String(company.name || '').toLowerCase()}</div>
                        )}
                    </div>
                    <div className="inv-meta">
                        <div className="inv-meta-top">
                            <h3 className="inv-number">Invoice #{invoice.number_display || invoice.id}</h3>
                            <span className={`inv-status ${invoice.status_class || ''}`}>{invoice.status_label}</span>
                        </div>
                        <div className="inv-dates">
                            <div>
                                <span className="inv-date-label">Invoice Date: </span>
                                <span className="small-text">{invoice.issue_date_display}</span>
                            </div>
                            <div>
                                <span className="inv-date-label">Invoice Due Date: </span>
                                <span className="small-text">{invoice.due_date_display}</span>
                            </div>
                            {invoice.paid_at_display ? (
                                <div>
                                    <span className="inv-date-label">Paid Date: </span>
                                    <span className="small-text">{invoice.paid_at_display}</span>
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

                <div className={`inv-parties${showPaymentPanel ? ' has-pay' : ''}`}>
                    <div className="inv-party">
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
                        <div className="inv-pay no-print">
                            <div className="inv-pay-title">Amount Due</div>
                            <div className="inv-pay-amount">{invoice.payable_amount_display}</div>
                            <div className="inv-pay-due">Due on {invoice.due_date_display}</div>

                            {invoice.pending_proof ? (
                                <div className="alert amber">Your payment proof is pending review.</div>
                            ) : null}
                            {!invoice.pending_proof && invoice.rejected_proof ? (
                                <div className="alert rose">Your last payment was rejected. Please pay again.</div>
                            ) : null}

                            {gateways.length === 0 ? (
                                <div className="inv-pay-note">No payment method is available right now.</div>
                            ) : (
                                <form method="POST" action={routes.checkout} id="gateway-form" target={gatewayTarget} data-native="true" style={{ margin: 0 }}>
                                    <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content || ''} />
                                    <div className="inv-pay-label">Choose payment method</div>
                                    <SearchableSelect
                                        name="payment_gateway_id"
                                        value={gatewayId}
                                        onChange={(nextValue) => setGatewayId(String(nextValue || ''))}
                                        options={gatewayOptions}
                                        placeholder="Select payment method"
                                        searchable={false}
                                        triggerClassName="!h-12 !rounded-xl !border-2 !border-teal-200 !bg-white !px-4 !text-[15px] !font-semibold !text-slate-900 hover:!border-teal-400"
                                        panelClassName="!rounded-xl"
                                        optionClassName="!px-4 !py-3 !text-sm"
                                    />
                                    {selectedGateway?.instructions ? (
                                        <div
                                            id="gateway-instructions"
                                            className="inv-instructions"
                                            dangerouslySetInnerHTML={{
                                                __html: toHtmlWithLineBreaks(selectedGateway.instructions),
                                            }}
                                        />
                                    ) : null}
                                    <button type="submit" id="gateway-submit" className="inv-pay-btn">
                                        {gatewayButtonLabel}
                                    </button>
                                </form>
                            )}

                            {payment_instructions ? (
                                <div className="inv-pay-note">{payment_instructions}</div>
                            ) : null}
                        </div>
                    ) : null}
                    <div className="inv-party right">
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

                <div className="panel">
                    <div className="table-responsive">
                        <table className="table">
                            <thead>
                                <tr>
                                    <td>
                                        <strong>Description</strong>
                                    </td>
                                    <td className="amount-col">
                                        <strong>Amount</strong>
                                    </td>
                                </tr>
                            </thead>
                            <tbody>
                                {(invoice.items || []).map((item) => (
                                    <tr key={item.id}>
                                        <td>{item.description}</td>
                                        <td className="amount-col">{item.line_total_display}</td>
                                    </tr>
                                ))}
                                <tr>
                                    <td className="total-row label">
                                        <strong>Sub Total</strong>
                                    </td>
                                    <td className="total-row amount-col">{invoice.subtotal_display}</td>
                                </tr>
                                {invoice.has_tax ? (
                                    <tr>
                                        <td className="total-row label">
                                            <strong>
                                                {invoice.tax_mode === 'inclusive' ? 'Included VAT' : tax.label} ({invoice.tax_rate_percent_display}%)
                                            </strong>
                                        </td>
                                        <td className="total-row amount-col">{invoice.tax_amount_display}</td>
                                    </tr>
                                ) : null}
                                <tr>
                                    <td className="total-row label">
                                        <strong>Discount</strong>
                                    </td>
                                    <td className="total-row amount-col">- {invoice.discount_display}</td>
                                </tr>
                                <tr>
                                    <td className="total-row label">
                                        <strong>Paid Amount</strong>
                                    </td>
                                    <td className="total-row amount-col">- {invoice.paid_amount_display}</td>
                                </tr>
                                <tr className="grand-total">
                                    <td className="total-row label">
                                        <strong>Total Due</strong>
                                    </td>
                                    <td className="total-row amount-col">{invoice.payable_amount_display}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {payments && payments.length > 0 && (
                    <div className="panel" style={{ marginTop: '20px' }}>
                        <div className="records-title">Payment Records</div>
                        <div className="table-responsive">
                            <table className="table records-table" style={{ marginBottom: 0 }}>
                                <thead style={{ background: '#f8fafc' }}>
                                    <tr>
                                        <td><strong>Date</strong></td>
                                        <td><strong>Payment Method</strong></td>
                                        <td><strong>Reference</strong></td>
                                        <td style={{ textAlign: 'center' }}><strong>Amount</strong></td>
                                    </tr>
                                </thead>
                                <tbody>
                                    {payments.map((payment) => (
                                        <tr key={payment.id}>
                                            <td data-label="Date">{payment.date_display}</td>
                                            <td data-label="Method">{payment.method}</td>
                                            <td data-label="Reference" style={{ overflowWrap: 'anywhere' }}>{payment.reference}</td>
                                            <td data-label="Amount" className="font-semibold text-emerald-700" style={{ textAlign: 'center' }}>
                                                {payment.amount_display}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                <div className="inv-actions no-print">
                    <a href={routes.download} data-native="true" className="inv-action">
                        Download
                    </a>
                    <button type="button" onClick={() => window.print()} className="inv-action">
                        Print
                    </button>
                </div>
                <p className="inv-footnote">This is system generated invoice no signature required</p>
            </div>
        </>
    );
}
