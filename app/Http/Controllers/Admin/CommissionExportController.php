<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CommissionEarning;
use App\Models\CommissionPayout;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CommissionExportController extends Controller
{
    public function exportEarnings(Request $request): StreamedResponse
    {
        $query = CommissionEarning::query()->with('salesRep');

        if ($request->filled('sales_rep_id')) {
            $query->where('sales_representative_id', $request->integer('sales_rep_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $from = $this->parseDate($request->input('from'));
        $to = $this->parseDate($request->input('to'));

        if ($from) {
            $query->whereDate('earned_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('earned_at', '<=', $to);
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=commission_earnings.csv',
        ];

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'id',
                'sales_rep',
                'source_type',
                'source_id',
                'invoice_id',
                'subscription_id',
                'project_id',
                'customer_id',
                'paid_amount',
                'commission_amount',
                'currency',
                'status',
                'earned_at',
                'payable_at',
                'paid_at',
                'reversed_at',
                'payout_id',
                'created_at',
                'updated_at',
            ]);

            $query->orderBy('id')->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->id,
                        $row->salesRep?->name,
                        $row->source_type,
                        $row->source_id,
                        $row->invoice_id,
                        $row->subscription_id,
                        $row->project_id,
                        $row->customer_id,
                        $row->paid_amount,
                        $row->commission_amount,
                        $row->currency,
                        $row->status,
                        optional($row->earned_at)->toDateTimeString(),
                        optional($row->payable_at)->toDateTimeString(),
                        optional($row->paid_at)->toDateTimeString(),
                        optional($row->reversed_at)->toDateTimeString(),
                        $row->commission_payout_id,
                        optional($row->created_at)->toDateTimeString(),
                        optional($row->updated_at)->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, 'commission_earnings.csv', $headers);
    }

    public function exportPayouts(Request $request): StreamedResponse
    {
        $query = CommissionPayout::query()->with('salesRep');

        if ($request->filled('sales_rep_id')) {
            $query->where('sales_representative_id', $request->integer('sales_rep_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $from = $this->parseDate($request->input('from'));
        $to = $this->parseDate($request->input('to'));

        if ($from) {
            $query->whereDate('paid_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('paid_at', '<=', $to);
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename=commission_payouts.csv',
        ];

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'id',
                'sales_rep',
                'total_amount',
                'currency',
                'status',
                'payout_method',
                'reference',
                'note',
                'paid_at',
                'reversed_at',
                'created_at',
                'updated_at',
            ]);

            $query->orderBy('id')->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->id,
                        $row->salesRep?->name,
                        $row->total_amount,
                        $row->currency,
                        $row->status,
                        $row->payout_method,
                        $row->reference,
                        $row->note,
                        optional($row->paid_at)->toDateTimeString(),
                        optional($row->reversed_at)->toDateTimeString(),
                        optional($row->created_at)->toDateTimeString(),
                        optional($row->updated_at)->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, 'commission_payouts.csv', $headers);
    }

    private function parseDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
