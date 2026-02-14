<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\PaidHoliday;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaidHolidayController extends Controller
{
    private const HOLIDAY_TYPES = [
        'Weekly holiday',
        'Festival/Public holidays',
        'Annual/Earned leave',
    ];

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $selectedMonth = $validated['month'] ?? now()->format('Y-m');
        $monthStart = Carbon::createFromFormat('Y-m', $selectedMonth)->startOfMonth();
        $monthEnd = (clone $monthStart)->endOfMonth();

        $holidays = PaidHoliday::query()
            ->whereBetween('holiday_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->orderBy('holiday_date')
            ->paginate(31)
            ->withQueryString();

        $totalDaysInMonth = (int) $monthEnd->day;
        $paidHolidayCount = PaidHoliday::query()
            ->where('is_paid', true)
            ->whereBetween('holiday_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();
        $workingDays = max(0, $totalDaysInMonth - $paidHolidayCount);
        $expectedHoursFullTime = $workingDays * 8;
        $expectedHoursPartTime = $workingDays * 4;

        $holidayTypes = self::HOLIDAY_TYPES;

        return view('admin.hr.paid-holidays.index', compact(
            'holidays',
            'selectedMonth',
            'holidayTypes',
            'totalDaysInMonth',
            'paidHolidayCount',
            'workingDays',
            'expectedHoursFullTime',
            'expectedHoursPartTime'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'holiday_date' => ['required', 'date_format:Y-m-d'],
            'name' => ['required', 'string', Rule::in(self::HOLIDAY_TYPES)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $month = Carbon::parse($data['holiday_date'])->format('Y-m');
        $existing = PaidHoliday::query()
            ->whereDate('holiday_date', $data['holiday_date'])
            ->first();

        if ($existing) {
            return redirect()
                ->route('admin.hr.paid-holidays.index', ['month' => $month])
                ->withErrors(['holiday_date' => 'The holiday date has already been taken.'])
                ->withInput();
        }

        PaidHoliday::create([
            'holiday_date' => $data['holiday_date'],
            'name' => trim($data['name']),
            'note' => isset($data['note']) ? trim((string) $data['note']) : null,
            'is_paid' => true,
        ]);

        return redirect()
            ->route('admin.hr.paid-holidays.index', ['month' => $month])
            ->with('status', 'Paid holiday saved.');
    }

    public function destroy(PaidHoliday $paidHoliday): RedirectResponse
    {
        $paidHoliday->delete();

        return back()->with('status', 'Paid holiday deleted.');
    }
}
