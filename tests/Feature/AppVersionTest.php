<?php

namespace Tests\Feature;

use App\Support\AppVersion;
use Tests\TestCase;

class AppVersionTest extends TestCase
{
    public function test_page_and_endpoint_report_the_same_build_version(): void
    {
        $version = AppVersion::current();
        $this->assertNotNull($version, 'public/build/manifest.json is required for this test.');

        $this->getJson(route('app.version'))
            ->assertOk()
            ->assertExactJson(['version' => $version])
            ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, post-check=0, pre-check=0, private');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<meta name="app-version" content="'.$version.'">', false)
            ->assertSee('/sw.js?v='.$version, false);
    }
}
