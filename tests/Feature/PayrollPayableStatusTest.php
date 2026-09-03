<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeCompensation;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The payroll grid recomputed each row's payable amount from attendance and
 * work logs. With no attendance recorded for the period that produced a ratio
 * of zero, wiping out the amount payroll generation had already calculated —
 * and a zero payable was then rendered as "Paid", so an unpaid draft period
 * showed every employee as settled and the Payment button disappeared.
 */
class PayrollPayableStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    #[Test]
    public function an_unpaid_item_is_never_shown_as_paid(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 09:00:00'));

        $employee = $this->createFullTimeEmployee(3500);
        app(PayrollService::class)->generatePeriod('2026-05');

        $period = PayrollPeriod::where('period_key', '2026-05')->firstOrFail();
        $item = PayrollItem::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame('draft', (string) $item->status);
        $this->assertSame(0.0, (float) $item->paid_amount);

        $row = $this->payrollRow($period, $item->id);

        $this->assertNotSame(
            'paid',
            $row['display_status'],
            'An item with no payment against it must never render as paid.'
        );
    }

    #[Test]
    public function a_period_with_no_attendance_data_still_shows_the_generated_amount(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 09:00:00'));

        $employee = $this->createFullTimeEmployee(3500);
        app(PayrollService::class)->generatePeriod('2026-05');

        $period = PayrollPeriod::where('period_key', '2026-05')->firstOrFail();
        $item = PayrollItem::where('employee_id', $employee->id)->firstOrFail();

        // Nothing was recorded in attendance for May.
        $this->assertSame(0, EmployeeAttendance::where('employee_id', $employee->id)->count());

        $row = $this->payrollRow($period, $item->id);

        $this->assertSame(
            (float) $item->net_pay,
            (float) str_replace(',', '', $row['payment_data']['net_amount']),
            'With no tracking data the payable amount must fall back to what generation calculated.'
        );
    }

    #[Test]
    public function recorded_absence_still_reduces_the_payable_amount(): void
    {
        // The fallback must not swallow a genuine zero: once attendance exists,
        // the pro-rata rule applies as before.
        Carbon::setTestNow(Carbon::parse('2026-06-15 09:00:00'));

        $employee = $this->createFullTimeEmployee(3100);
        app(PayrollService::class)->generatePeriod('2026-05');

        $period = PayrollPeriod::where('period_key', '2026-05')->firstOrFail();
        $item = PayrollItem::where('employee_id', $employee->id)->firstOrFail();

        // 31 days in May, all marked absent.
        for ($day = 1; $day <= 31; $day++) {
            EmployeeAttendance::create([
                'employee_id' => $employee->id,
                'date' => sprintf('2026-05-%02d', $day),
                'status' => 'absent',
            ]);
        }

        $row = $this->payrollRow($period, $item->id);

        $this->assertSame(
            0.0,
            (float) str_replace(',', '', $row['payment_data']['net_amount']),
            'A month of recorded absence should still pro-rate down to nothing.'
        );
        $this->assertSame('nothing_payable', $row['display_status']);
        $this->assertFalse($row['can_pay']);
    }

    #[Test]
    public function an_item_with_a_payable_amount_can_be_paid(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 09:00:00'));

        $employee = $this->createFullTimeEmployee(3500);
        app(PayrollService::class)->generatePeriod('2026-05');

        $period = PayrollPeriod::where('period_key', '2026-05')->firstOrFail();
        $item = PayrollItem::where('employee_id', $employee->id)->firstOrFail();
        $item->update(['status' => 'approved']);

        $row = $this->payrollRow($period, $item->id);

        $this->assertSame('approved', $row['display_status']);
        $this->assertTrue(
            $row['can_pay'],
            'An approved item with money owed must offer the Payment action.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payrollRow(PayrollPeriod $period, int $itemId): array
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $response = $this->actingAs($admin)->get(route('admin.hr.payroll.show', $period));
        $response->assertOk();

        // The prop is still a paginator at this point, not the serialized array.
        $items = $response->viewData('page')['props']['items'];
        $rows = $items instanceof \Illuminate\Contracts\Pagination\Paginator
            ? $items->items()
            : (is_array($items) ? ($items['data'] ?? $items) : (array) $items);

        $row = collect($rows)->firstWhere('id', $itemId);

        $this->assertNotNull($row, "Payroll item {$itemId} was not rendered.");

        return $row;
    }

    private function createFullTimeEmployee(float $monthlyPay): Employee
    {
        $user = User::factory()->create();

        $employee = Employee::create([
            'user_id' => $user->id,
            'name' => 'Payroll Staff',
            'email' => 'payroll-staff-'.uniqid().'@example.test',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_mode' => 'onsite',
            'join_date' => '2026-01-01',
        ]);

        EmployeeCompensation::create([
            'employee_id' => $employee->id,
            'salary_type' => 'monthly',
            'currency' => 'BDT',
            'basic_pay' => $monthlyPay,
            'overtime_rate' => 0,
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        return $employee;
    }
}
