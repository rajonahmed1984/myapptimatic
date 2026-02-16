<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginNoCacheHeadersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array<int, string>>
     */
    public static function loginPortalRoutes(): array
    {
        return [
            ['login', 'login.attempt'],
            ['admin.login', 'admin.login.attempt'],
            ['employee.login', 'employee.login.attempt'],
            ['sales.login', 'sales.login.attempt'],
            ['support.login', 'support.login.attempt'],
        ];
    }

    /**
     * @dataProvider loginPortalRoutes
     */
    public function test_login_get_has_no_cache_headers(string $loginRouteName, string $attemptRouteName): void
    {
        $response = $this->get(route($loginRouteName));

        $response->assertOk();
        $this->assertNoCacheHeaders($response);
    }

    /**
     * @dataProvider loginPortalRoutes
     */
    public function test_login_post_has_no_cache_headers(string $loginRouteName, string $attemptRouteName): void
    {
        $response = $this->from(route($loginRouteName))->post(route($attemptRouteName), [
            'email' => 'not-an-email',
            'password' => '',
        ]);

        $response->assertStatus(302);
        $this->assertNoCacheHeaders($response);
    }

    private function assertNoCacheHeaders(\Illuminate\Testing\TestResponse $response): void
    {
        $cacheControl = strtolower((string) $response->headers->get('Cache-Control', ''));
        $pragma = strtolower((string) $response->headers->get('Pragma', ''));
        $expires = (string) $response->headers->get('Expires', '');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertSame('no-cache', $pragma);
        $this->assertSame('0', $expires);
    }
}
