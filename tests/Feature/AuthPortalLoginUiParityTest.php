<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthPortalLoginUiParityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('recaptcha.enabled', false);
    }

    #[Test]
    public function portal_login_pages_render_inertia_component(): void
    {
        foreach (['login', 'admin.login', 'employee.login', 'sales.login', 'support.login'] as $routeName) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertSee('data-page=')
                ->assertSee('Auth\\/PortalLogin', false);
        }
    }

    #[Test]
    public function portal_login_pages_keep_submit_and_forgot_routes_in_props(): void
    {
        $cases = [
            ['login', 'web', route('login.attempt', [], false), route('password.request', [], false)],
            ['admin.login', 'admin', route('admin.login.attempt', [], false), route('admin.password.request', [], false)],
            ['employee.login', 'employee', route('employee.login.attempt', [], false), route('employee.password.request', [], false)],
            ['sales.login', 'sales', route('sales.login.attempt', [], false), route('sales.password.request', [], false)],
            ['support.login', 'support', route('support.login.attempt', [], false), route('support.password.request', [], false)],
        ];

        foreach ($cases as [$routeName, $portal, $submit, $forgot]) {
            $response = $this->get(route($routeName));
            $props = $this->inertiaProps($response->getContent());

            $this->assertSame($portal, data_get($props, 'portal'));
            $this->assertSame($submit, data_get($props, 'routes.submit'));
            $this->assertSame($forgot, data_get($props, 'routes.forgot'));
        }
    }

    #[Test]
    public function web_login_keeps_redirect_query_in_form_props(): void
    {
        $response = $this->get(route('login', ['redirect' => '/client/orders']));
        $props = $this->inertiaProps($response->getContent());

        $this->assertSame('/client/orders', data_get($props, 'form.redirect'));
        $this->assertStringContainsString('redirect=%2Fclient%2Forders', (string) data_get($props, 'hint.href'));
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
