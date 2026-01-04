<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\MilestoneInvoiceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MilestoneController extends Controller
{
    public function store(Request $request, Order $order, MilestoneInvoiceService $milestones): RedirectResponse
    {
        $this->authorize('admin'); // simplistic gate; adjust if using policies/roles

        $data = $request->validate([
            'advance_percent' => ['required', 'integer', 'min:1', 'max:99'],
            'final_percent' => ['required', 'integer', 'min:1', 'max:99'],
            'advance_due_date' => ['required', 'date'],
            'final_due_date' => ['required', 'date', 'after_or_equal:advance_due_date'],
        ]);

        try {
            [$advance, $final] = $milestones->createMilestones(
                $order,
                $data['advance_percent'],
                $data['final_percent'],
                Carbon::parse($data['advance_due_date']),
                Carbon::parse($data['final_due_date'])
            );
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return back()->withErrors($ve->errors());
        } catch (\Throwable $e) {
            return back()->withErrors(['milestones' => $e->getMessage()]);
        }

        return back()->with('status', "Milestone invoices created: Advance #{$advance->id}, Final #{$final->id}.");
    }
}
