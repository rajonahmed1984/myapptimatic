<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaleImpersonationBannerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_signed_in_as_themselves_is_not_flagged_as_impersonating(): void
    {
        $admin = User::factory()->create(['role' => 'master_admin']);

        $response = $this->actingAs($admin)
            ->withSession(['impersonator_id' => $admin->id])
            ->get(route('admin.projects.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('auth.is_impersonating', false));
        $response->assertSessionMissing('impersonator_id');
    }

    #[Test]
    public function real_impersonation_still_shows_the_banner(): void
    {
        $admin = User::factory()->create(['role' => 'master_admin']);
        $other = User::factory()->create(['role' => 'master_admin']);

        $response = $this->actingAs($other)
            ->withSession(['impersonator_id' => $admin->id])
            ->get(route('admin.projects.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('auth.is_impersonating', true));
    }
}
