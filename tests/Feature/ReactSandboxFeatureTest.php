<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReactSandboxFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_react_sandbox_is_not_accessible_when_flag_is_off(): void
    {
        config(['features.react_sandbox' => false]);

        $this->get('/__ui/react-sandbox')->assertNotFound();
    }

    public function test_react_sandbox_renders_when_flag_is_on(): void
    {
        config(['features.react_sandbox' => true]);

        $response = $this->get('/__ui/react-sandbox');

        $response->assertOk();
        $response->assertSee('&quot;component&quot;:&quot;Sandbox&quot;', false);
        $response->assertSee('data-page=');
    }
}
