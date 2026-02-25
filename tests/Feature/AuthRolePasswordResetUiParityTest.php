<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthRolePasswordResetUiParityTest extends TestCase
{
    #[Test]
    public function role_forgot_password_pages_render_inertia_component_with_expected_props(): void
    {
        $cases = [
            ['employee.password.request', 'employee', route('employee.password.email', [], false), route('employee.login', [], false)],
            ['sales.password.request', 'sales', route('sales.password.email', [], false), route('sales.login', [], false)],
            ['support.password.request', 'support', route('support.password.email', [], false), route('support.login', [], false)],
        ];

        foreach ($cases as [$routeName, $role, $emailRoute, $loginRoute]) {
            $response = $this->get(route($routeName));
            $props = $this->inertiaProps($response->getContent());

            $response
                ->assertOk()
                ->assertSee('data-page=')
                ->assertSee('Auth\\/ForgotPassword', false);

            $this->assertSame($emailRoute, data_get($props, 'routes.email'));
            $this->assertSame($loginRoute, data_get($props, 'routes.login'));
            $this->assertTrue((bool) data_get($props, 'messages.email_error_warning'));
            $this->assertStringContainsString(ucfirst($role), (string) data_get($props, 'pageTitle'));
        }
    }

    #[Test]
    public function role_reset_password_pages_render_inertia_component_with_expected_props(): void
    {
        $cases = [
            ['employee.password.reset', 'employee', route('employee.password.update', [], false), route('employee.login', [], false)],
            ['sales.password.reset', 'sales', route('sales.password.update', [], false), route('sales.login', [], false)],
            ['support.password.reset', 'support', route('support.password.update', [], false), route('support.login', [], false)],
        ];

        foreach ($cases as [$routeName, $role, $submitRoute, $loginRoute]) {
            $response = $this->get(route($routeName, ['token' => 'sample-token', 'email' => 'person@example.test']));
            $props = $this->inertiaProps($response->getContent());

            $response
                ->assertOk()
                ->assertSee('data-page=')
                ->assertSee('Auth\\/ResetPassword', false);

            $this->assertSame($submitRoute, data_get($props, 'routes.submit'));
            $this->assertSame($loginRoute, data_get($props, 'routes.login'));
            $this->assertSame('sample-token', data_get($props, 'form.token'));
            $this->assertSame('person@example.test', data_get($props, 'form.email'));
            $this->assertStringContainsString(ucfirst($role), (string) data_get($props, 'pageTitle'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function inertiaProps(string $html): array
    {
        preg_match('/data-page="([^"]+)"/', $html, $matches);
        $this->assertArrayHasKey(1, $matches, 'Inertia payload is missing in response.');

        $decoded = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        $payload = json_decode($decoded, true);
        $this->assertIsArray($payload);

        $props = data_get($payload, 'props', []);
        $this->assertIsArray($props);

        return $props;
    }
}
