<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PwaInstallPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_can_open_the_install_page(): void
    {
        $this->get('/install')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Public/InstallApp', false));
    }

    #[Test]
    public function the_manifest_service_worker_and_icons_are_present(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.json')), true);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertNotEmpty($manifest['icons']);
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('icons/apple-touch-icon.png'));
    }
}
