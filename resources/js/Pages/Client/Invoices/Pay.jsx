import React, { useMemo, useState } from 'react';
import { Head } from '@inertiajs/react';

const toHtmlWithLineBreaks = (value) => String(value || '').replace(/\n/g, '<br>');

export default function Pay({
    invoice = {},
    tax = {},
    company = {},
    gateways = [],
    payment_instructions = '',
    routes = {},
}) {
    const initialGatewayId = gateways.length > 0 ? String(gateways[0].id) : '';
    const [gatewayId, setGatewayId] = useState(initialGatewayId);

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
    const showPaymentPanel = String(invoice.status || '').toLowerCase() !== 'paid';

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

                <div className="invoice-grid invoice-addresses">
                    <div className="invoice-col">
                        <strong>Invoiced To</strong>
                        <address className="small-text">
                            {invoice?.customer?.name || '--'}
                            <br />
                            {invoice?.customer?.email || '--'}
                            <br />
                            {invoice?.customer?.address || '--'}
                        </address>
                    </div>
                    <div className="invoice-col right">
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
                            {tax.note ? <div className="small-text text-muted">{tax.note}</div> : null}
                        </div>
                    </div>
                </div>

                {showPaymentPanel ? (
                    <div className="payment-panel no-print">
                        <div className="payment-heading">Payment Method</div>
                        {invoice.pending_proof ? <div className="alert amber">Manual payment submitted and pending review.</div> : null}
                        {!invoice.pending_proof && invoice.rejected_proof ? (
                            <div className="alert rose">Manual payment was rejected. Please submit a new transfer.</div>
                        ) : null}

                        {gateways.length === 0 ? (
                            <div className="small-text text-muted">No active payment gateways configured.</div>
                        ) : (
                            <form method="POST" action={routes.checkout} id="gateway-form" className="gateway-form" target={gatewayTarget} data-native="true">
                                <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.content || ''} />
                                <label htmlFor="gateway-select" className="small-text">
                                    <strong>Select gateway</strong>
                                </label>
                                <select
                                    id="gateway-select"
                                    name="payment_gateway_id"
                                    className="form-control"
                                    value={gatewayId}
                                    onChange={(event) => setGatewayId(event.target.value)}
                                >
                                    {gateways.map((gateway) => (
                                        <option key={gateway.id} value={gateway.id}>
                                            {gateway.name}
                                        </option>
                                    ))}
                                </select>
                                <div
                                    id="gateway-instructions"
                                    className="small-text text-muted"
                                    style={{ marginTop: 10 }}
                                    dangerouslySetInnerHTML={{
                                        __html: selectedGateway?.instructions
                                            ? toHtmlWithLineBreaks(selectedGateway.instructions)
                                            : 'No additional instructions for this gateway.',
                                    }}
                                />
                                <button type="submit" id="gateway-submit" className="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                                    {gatewayButtonLabel}
                                </button>
                            </form>
                        )}

                        {payment_instructions ? (
                            <div className="small-text text-muted whitespace-pre-line" style={{ marginTop: 12 }}>
                                {payment_instructions}
                            </div>
                        ) : null}
                    </div>
                ) : null}

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
