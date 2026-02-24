<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HrPayrollUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function payroll_index_renders_direct_inertia_component_for_admin(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        PayrollPeriod::create([
            'period_key' => '2025-01',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.hr.payroll.index'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Hr\\/Payroll\\/Index', false);
    }

    #[Test]
    public function payroll_edit_and_show_render_direct_inertia_components_for_admin(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $period = PayrollPeriod::create([
            'period_key' => '2025-01',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.hr.payroll.edit', $period))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Hr\\/Payroll\\/Edit', false);

        $this->actingAs($admin)
            ->get(route('admin.hr.payroll.show', $period))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Hr\\/Payroll\\/Show', false);
    }

    #[Test]
    public function payroll_generate_and_destroy_contracts_are_preserved(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $periodKey = '2025-02';

        $this->actingAs($admin)
            ->post(route('admin.hr.payroll.generate'), [
                'period_key' => $periodKey,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Payroll generated for '.$periodKey);

        $period = PayrollPeriod::query()->where('period_key', $periodKey)->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('admin.hr.payroll.destroy', $period))
            ->assertRedirect()
            ->assertSessionHas('status', 'Payroll period deleted: '.$periodKey);
    }

    #[Test]
    public function payroll_update_contract_is_preserved(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $period = PayrollPeriod::create([
            'period_key' => '2025-03',
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-31',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.hr.payroll.update', $period), [
                'period_key' => '2025-04',
                'start_date' => '2025-04-01',
                'end_date' => '2025-04-30',
            ])
            ->assertRedirect(route('admin.hr.payroll.index'))
            ->assertSessionHas('status', 'Payroll period updated.');
    }

    #[Test]
    public function payroll_index_remains_forbidden_for_client_role(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        $period = PayrollPeriod::create([
            'period_key' => '2025-05',
            'start_date' => '2025-05-01',
            'end_date' => '2025-05-31',
            'status' => 'draft',
        ]);

        $this->actingAs($client)
            ->get(route('admin.hr.payroll.index'))
            ->assertForbidden();

        $this->actingAs($client)
            ->get(route('admin.hr.payroll.show', $period))
            ->assertForbidden();
    }
}
