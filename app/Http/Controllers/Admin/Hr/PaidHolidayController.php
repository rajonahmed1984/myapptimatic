<?php

namespace App\Http\Controllers\Admin\Hr;

use App\Http\Controllers\Controller;
use App\Models\PaidHoliday;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PaidHolidayController extends Controller
{
    private const HOLIDAY_TYPES = [
        'Weekly holiday',
        'Festival/Public holidays',
        'Annual/Earned leave',
    ];

    public function index(Request $request): InertiaResponse
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

        return Inertia::render('Admin/Hr/PaidHolidays/Index', [
            'pageTitle' => 'Paid Holidays',
            'selectedMonth' => $selectedMonth,
            'holidayTypes' => $holidayTypes,
            'holidays' => $holidays->through(fn (PaidHoliday $holiday) => [
                'id' => $holiday->id,
                'holiday_date' => $holiday->holiday_date?->format(config('app.date_format', 'd-m-Y')) ?? '--',
                'name' => $holiday->name,
                'note' => $holiday->note,
                'is_paid' => (bool) $holiday->is_paid,
                'routes' => [
                    'destroy' => route('admin.hr.paid-holidays.destroy', $holiday),
                ],
            ])->values(),
            'summary' => [
                'totalDaysInMonth' => $totalDaysInMonth,
                'paidHolidayCount' => $paidHolidayCount,
                'workingDays' => $workingDays,
                'expectedHoursFullTime' => $expectedHoursFullTime,
                'expectedHoursPartTime' => $expectedHoursPartTime,
            ],
            'pagination' => [
                'previous_url' => $holidays->previousPageUrl(),
                'next_url' => $holidays->nextPageUrl(),
                'has_pages' => $holidays->hasPages(),
            ],
            'routes' => [
                'index' => route('admin.hr.paid-holidays.index'),
                'store' => route('admin.hr.paid-holidays.store'),
            ],
        ]);
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
