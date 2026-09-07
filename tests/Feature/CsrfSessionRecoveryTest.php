<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CsrfSessionRecoveryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function csrf_token_endpoint_hands_out_a_usable_token(): void
    {
        $response = $this->get('/csrf-token');

        $response->assertOk();
        $response->assertJsonStructure(['token', 'authenticated', 'lifetime']);
        $this->assertNotSame('', (string) $response->json('token'));
        $this->assertFalse($response->json('authenticated'));
    }

    #[Test]
    public function csrf_token_endpoint_reports_an_authenticated_session(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);

        $response = $this->actingAs($admin)->get('/csrf-token');

        $response->assertOk();
        $this->assertTrue($response->json('authenticated'));
    }

    #[Test]
    public function a_guest_hitting_a_stale_token_is_not_told_the_session_expired(): void
    {
        $this->registerFailingRoute('/__test/csrf/guest');

        $response = $this->from('/login')->post('/__test/csrf/guest');

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $this->assertStringNotContainsString('Session expired', (string) session('status'));
    }

    #[Test]
    public function an_authenticated_user_hitting_a_stale_token_is_logged_out(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $this->registerFailingRoute('/__test/csrf/auth');

        $response = $this->actingAs($admin)->post('/__test/csrf/auth');

        $response->assertRedirect();
        $this->assertStringContainsString('Session expired', (string) session('status'));
        $this->assertGuest();
    }

    #[Test]
    public function a_json_request_with_a_stale_token_gets_a_fresh_one_back(): void
    {
        $this->registerFailingRoute('/__test/csrf/json');

        $response = $this->postJson('/__test/csrf/json');

        $response->assertStatus(419);
        $this->assertNotSame('', (string) $response->json('csrf_token'));
    }

    #[Test]
    public function an_inertia_request_gets_a_location_response_it_can_follow(): void
    {
        $admin = User::factory()->create(['role' => Role::MASTER_ADMIN]);
        $this->registerFailingRoute('/__test/csrf/inertia');

        $response = $this->actingAs($admin)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post('/__test/csrf/inertia');

        $response->assertStatus(409);
        $this->assertNotSame('', (string) $response->headers->get('X-Inertia-Location'));
    }

    /**
     * The CSRF middleware never runs under the testing environment, so raise the
     * exception it would have raised and assert on how the app answers it.
     */
    private function registerFailingRoute(string $uri): void
    {
        Route::middleware('web')->post($uri, function (): void {
            throw new TokenMismatchException('CSRF token mismatch.');
        });
    }
}
