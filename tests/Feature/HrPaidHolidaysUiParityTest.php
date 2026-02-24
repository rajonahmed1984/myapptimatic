<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\PaidHoliday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HrPaidHolidaysUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function paid_holidays_index_renders_direct_inertia_component_for_admin(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        PaidHoliday::create([
            'holiday_date' => now()->startOfMonth()->addDays(3)->toDateString(),
            'name' => 'Weekly holiday',
            'note' => 'Weekly rest day',
            'is_paid' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.hr.paid-holidays.index'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Hr\\/PaidHolidays\\/Index', false);
    }

    #[Test]
    public function paid_holiday_store_and_destroy_contracts_are_preserved(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $date = now()->startOfMonth()->addDays(5)->toDateString();

        $this->actingAs($admin)
            ->post(route('admin.hr.paid-holidays.store'), [
                'holiday_date' => $date,
                'name' => 'Festival/Public holidays',
                'note' => 'UI parity save',
            ])
            ->assertRedirect(route('admin.hr.paid-holidays.index', ['month' => now()->format('Y-m')]))
            ->assertSessionHas('status', 'Paid holiday saved.');

        $holiday = PaidHoliday::query()->whereDate('holiday_date', $date)->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('admin.hr.paid-holidays.destroy', $holiday))
            ->assertRedirect()
            ->assertSessionHas('status', 'Paid holiday deleted.');
    }

    #[Test]
    public function paid_holidays_index_remains_forbidden_for_client_role(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        $this->actingAs($client)
            ->get(route('admin.hr.paid-holidays.index'))
            ->assertForbidden();
    }
}
