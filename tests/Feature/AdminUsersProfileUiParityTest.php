<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminUsersProfileUiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_users_and_profile_pages_render_inertia_components(): void
    {
        $admin = User::factory()->create([
            'role' => Role::MASTER_ADMIN,
        ]);

        $editableUser = User::factory()->create([
            'role' => Role::SUPPORT,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.users.index', Role::MASTER_ADMIN))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Users\\/Index', false);

        $this->actingAs($admin)
            ->get(route('admin.users.create', Role::MASTER_ADMIN))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Users\\/Form', false);

        $this->actingAs($admin)
            ->get(route('admin.users.edit', $editableUser))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Users\\/Form', false);

        $this->actingAs($admin)
            ->get(route('admin.admins.index'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Users\\/Index', false);

        $this->actingAs($admin)
            ->get(route('admin.profile.edit'))
            ->assertOk()
            ->assertSee('data-page=')
            ->assertSee('Admin\\/Profile\\/Edit', false);
    }
}
