@extends('layouts.admin')

@section('title', 'Invoice Details')
@section('page-title', 'Invoice Details')

@section('content')
    @include('admin.invoices.partials.show-main', ['invoice' => $invoice])
@endsection

@push('styles')
    <style>
        .invoice-container { width: 100%; background: #fff; padding: 10px; color: #333; font-family: "Helvetica Neue", Helvetica, Arial, sans-serif; }
        .invoice-container .row { display: flex; flex-wrap: wrap; margin: 0 -15px; }
        .invoice-container .invoice-col { width: 50%; padding: 0 15px; }
        .invoice-container .invoice-col.full { width: 100%; }
        .invoice-container .invoice-col.right { text-align: right; }
        .invoice-container .logo-wrap { display: flex; align-items: center; }
        .invoice-container .invoice-logo { width: 300px; max-width: 100%; height: auto; }
        .invoice-container .invoice-logo-fallback { font-size: 54px; font-weight: 800; color: #211f75; letter-spacing: -1px; line-height: 1; }
        .invoice-container .invoice-status { margin-top: 8px; font-size: 24px; font-weight: 700; }
        .invoice-container .invoice-status h3 { margin: 0; font-size: 18px; font-weight: 600; }
        .invoice-container .invoice-status .small-text { font-size: 12px; }
        .invoice-container hr { border: 0; border-top: 1px solid #eee; margin: 20px 0; }
        .invoice-container address { margin: 8px 0 0; font-style: normal; line-height: 1.5; }
        .invoice-container .small-text { font-size: 0.92em; }
        .invoice-container .panel { margin-top: 14px; background: #fff; }
        .invoice-container .panel-heading { padding: 0 0 8px; background: transparent; border: 0; }
        .invoice-container .panel-title { margin: 0; font-size: 16px; }
        .invoice-container .table-responsive { width: 100%; overflow-x: auto; }
        .invoice-container .table { width: 100%; border-collapse: collapse; }
        .invoice-container .table > thead > tr > td,
        .invoice-container .table > tbody > tr > td { border: 1px solid #ddd; padding: 8px; }
        .invoice-container .text-right { text-align: right; }
        .invoice-container .text-center { text-align: center; }
        .invoice-container .unpaid, .invoice-container .overdue { color: #cc0000; text-transform: uppercase; }
        .invoice-container .paid { color: #779500; text-transform: uppercase; }
        .invoice-container .cancelled, .invoice-container .refunded { color: #888; text-transform: uppercase; }
        .invoice-container .mt-5 { margin-top: 50px; }
        @media (max-width: 767px) {
            .invoice-container .invoice-col { width: 100%; }
            .invoice-container .invoice-col.right { text-align: left; margin-top: 14px; }
        }
    </style>
@endpush

@push('scripts')
    <script data-script-key="admin-invoice-show-toggle">
        (() => {
            if (window.__adminInvoiceToggleInit) {
                return;
            }

            window.__adminInvoiceToggleInit = true;
            document.addEventListener('click', (event) => {
                const toggle = event.target.closest('#manage-invoice-toggle');
                if (!toggle) {
                    return;
                }

                event.preventDefault();
                const panel = document.getElementById('manage-invoice-panel');
                if (!panel) {
                    return;
                }

                panel.classList.toggle('hidden');
                if (!panel.classList.contains('hidden')) {
                    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        })();
    </script>
@endpush

