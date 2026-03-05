<?php

namespace Tests\Feature\Smoke;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PortalEntryInertiaSmokeTest extends TestCase
{
    #[Test]
    public function portal_entry_routes_resolve_to_inertia_login_pages(): void
    {
        $cases = [
            ['/admin', route('admin.login')],
            [route('employee.home'), route('employee.login')],
            [route('sales.home'), route('sales.login')],
            [route('support.home'), route('support.login')],
        ];

        foreach ($cases as [$entryUrl, $loginUrl]) {
            $this->get($entryUrl)->assertRedirect($loginUrl);

            $response = $this->followingRedirects()
                ->get($entryUrl)
                ->assertOk()
                ->assertSee('data-page=');

            $payload = $this->inertiaPayload($response->getContent());
            $this->assertSame('Auth/PortalLogin', data_get($payload, 'component'));
        }
    }

    #[Test]
    public function auth_entry_pages_render_expected_inertia_components(): void
    {
        $cases = [
            ['login', 'Auth/PortalLogin'],
            ['admin.login', 'Auth/PortalLogin'],
            ['employee.login', 'Auth/PortalLogin'],
            ['sales.login', 'Auth/PortalLogin'],
            ['support.login', 'Auth/PortalLogin'],
            ['register', 'Auth/Register'],
            ['project-client.login', 'Auth/ProjectLogin'],
            ['password.request', 'Auth/ForgotPassword'],
            ['admin.password.request', 'Auth/ForgotPassword'],
            ['employee.password.request', 'Auth/ForgotPassword'],
            ['sales.password.request', 'Auth/ForgotPassword'],
            ['support.password.request', 'Auth/ForgotPassword'],
        ];

        foreach ($cases as [$routeName, $component]) {
            $response = $this->get(route($routeName))
                ->assertOk()
                ->assertSee('data-page=');

            $payload = $this->inertiaPayload($response->getContent());
            $this->assertSame($component, data_get($payload, 'component'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function inertiaPayload(string $html): array
    {
        preg_match('/data-page="([^"]+)"/', $html, $matches);
        $this->assertArrayHasKey(1, $matches, 'Inertia payload is missing in response.');

        $decoded = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        $payload = json_decode($decoded, true);
        $this->assertIsArray($payload);

        return $payload;
    }
}
