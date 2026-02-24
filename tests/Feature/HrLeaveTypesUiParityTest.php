<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HrLeaveTypesUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function leave_types_index_renders_direct_inertia_component_for_admin(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        LeaveType::create([
            'name' => 'Annual Leave',
            'code' => 'AL',
            'is_paid' => true,
            'default_allocation' => 14,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.hr.leave-types.index'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Hr\\/LeaveTypes\\/Index', false);
    }

    #[Test]
    public function leave_type_store_update_destroy_contracts_are_preserved(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.hr.leave-types.store'), [
                'name' => 'Sick Leave',
                'code' => 'SL',
                'is_paid' => '1',
                'default_allocation' => '10',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Leave type saved.');

        $leaveType = LeaveType::query()->where('code', 'SL')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.hr.leave-types.update', $leaveType), [
                'name' => 'Sick Leave Updated',
                'code' => 'SL',
                'default_allocation' => '8',
            ])
            ->assertRedirect(route('admin.hr.leave-types.index'))
            ->assertSessionHas('status', 'Leave type updated.');

        $this->actingAs($admin)
            ->delete(route('admin.hr.leave-types.destroy', $leaveType))
            ->assertRedirect()
            ->assertSessionHas('status', 'Leave type deleted.');
    }

    #[Test]
    public function leave_types_index_remains_forbidden_for_client_role(): void
    {
        $client = User::factory()->create([
            'role' => Role::CLIENT,
        ]);

        $this->actingAs($client)
            ->get(route('admin.hr.leave-types.index'))
            ->assertForbidden();
    }
}
