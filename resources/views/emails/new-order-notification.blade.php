@extends('emails.layout')

@section('content')
    <div style="font-family:Arial, sans-serif;font-size:14px;line-height:1.6;color:#334155;">
        <p><strong>Order Information</strong></p>
        <p>
            Order ID: {{ $order->id }}<br>
            Order Number: {{ $orderNumber }}<br>
            Date/Time: {{ $order->created_at?->format($dateFormat . ' h:i A') ?? '--' }} ({{ $timeZone }})<br>
            Invoice Number: {{ $order->invoice ? (is_numeric($order->invoice->number) ? $order->invoice->number : $order->invoice->id) : '--' }}<br>
            Payment Method: {{ $paymentMethod }}
        </p>

        <p><strong>Customer Information</strong></p>
        <p>
            Customer ID: {{ $order->customer?->id ?? '--' }}<br>
            Name: {{ $order->customer?->name ?? '--' }}<br>
            Email:
            @if($order->customer?->email)
                <a href="mailto:{{ $order->customer->email }}">{{ $order->customer->email }}</a>
            @else
                --
            @endif
            <br>
            Company: {{ $order->customer?->company_name ?? '--' }}<br>
            Address: {!! nl2br(e($order->customer?->address ?? '--')) !!}<br>
            Phone Number: {{ $order->customer?->phone ?? '--' }}
        </p>

        <p><strong>Order Items</strong></p>
        @if($order->invoice && $order->invoice->items->isNotEmpty())
            <table cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
                <thead>
                    <tr>
                        <th align="left" style="padding:8px 6px;border-bottom:1px solid #e2e8f0;font-size:12px;color:#64748b;">Item</th>
                        <th align="right" style="padding:8px 6px;border-bottom:1px solid #e2e8f0;font-size:12px;color:#64748b;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->invoice->items as $item)
                        <tr>
                            <td style="padding:8px 6px;border-bottom:1px solid #f1f5f9;">
                                {{ $item->description }}
                            </td>
                            <td align="right" style="padding:8px 6px;border-bottom:1px solid #f1f5f9;">
                                {{ $order->invoice->currency }} {{ number_format((float) $item->line_total, 2) }}
                            </td>
                        </tr>
                    @endforeach
                    <tr>
                        <td style="padding:10px 6px;font-weight:bold;">Total Due Today</td>
                        <td align="right" style="padding:10px 6px;font-weight:bold;">
                            {{ $orderTotal }}
                        </td>
                    </tr>
                </tbody>
            </table>
        @else
            <p>{{ $serviceName }}<br>Total Due Today: {{ $orderTotal }}</p>
        @endif

        <p><strong>ISP Information</strong></p>
        <p>
            IP: {{ $ipAddress }}<br>
            Host: {{ $host }}
        </p>

        @if(!empty(trim($bodyHtml)))
            <div style="margin-top:12px;">
                {!! $bodyHtml !!}
            </div>
        @endif

        <p>
            <a href="{{ $orderUrl }}">{{ $orderUrl }}</a>
        </p>
    </div>
@endsection
