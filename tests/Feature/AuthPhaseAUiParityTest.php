<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthPhaseAUiParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('recaptcha.enabled', false);
    }

    #[Test]
    public function phase_a_auth_pages_render_inertia_components(): void
    {
        $cases = [
            [route('register'), 'Auth/Register'],
            [route('password.request'), 'Auth/ForgotPassword'],
            [route('admin.password.request'), 'Auth/ForgotPassword'],
            [route('password.reset', 'token-sample'), 'Auth/ResetPassword'],
            [route('project-client.login'), 'Auth/ProjectLogin'],
        ];

        foreach ($cases as [$url, $component]) {
            $this->get($url)
                ->assertOk()
                ->assertSee('data-page=')
                ->assertSee(str_replace('/', '\\/', $component), false);
        }
    }

    #[Test]
    public function register_page_preserves_redirect_query_in_inertia_props(): void
    {
        $response = $this->get(route('register', ['redirect' => '/client/orders']));
        $props = $this->inertiaProps($response->getContent());

        $this->assertSame('/client/orders', data_get($props, 'form.redirect'));
        $this->assertStringContainsString('redirect=%2Fclient%2Forders', (string) data_get($props, 'routes.login'));
    }

    #[Test]
    public function forgot_password_invalid_payload_keeps_redirect_and_validation_contract(): void
    {
        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'not-an-email',
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHasErrors(['email']);
    }

    #[Test]
    public function admin_forgot_password_props_keep_portal_routes(): void
    {
        $response = $this->get(route('admin.password.request'));
        $props = $this->inertiaProps($response->getContent());

        $this->assertSame(route('admin.password.email', [], false), data_get($props, 'routes.email'));
        $this->assertSame(route('admin.login', [], false), data_get($props, 'routes.login'));
    }

    #[Test]
    public function project_client_login_failure_keeps_redirect_and_errors_contract(): void
    {
        User::factory()->create([
            'email' => 'project-client@example.test',
            'password' => 'secret-pass',
            'role' => Role::CLIENT_PROJECT,
        ]);

        $response = $this->from(route('project-client.login'))->post(route('project-client.login.attempt'), [
            'email' => 'project-client@example.test',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('project-client.login'));
        $response->assertSessionHasErrors(['email']);
        $response->assertSessionHasInput(['email' => 'project-client@example.test']);
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
