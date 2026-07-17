<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductionReadinessCommandTest extends TestCase
{
    #[Test]
    public function readiness_command_is_read_only_and_reports_actionable_checks(): void
    {
        $this->artisan('performance:readiness')
            ->expectsOutputToContain('Application environment')
            ->expectsOutputToContain('Debug mode')
            ->expectsOutputToContain('Vite production manifest')
            ->expectsOutputToContain('OPcache (CLI signal)')
            ->assertSuccessful();
    }

    #[Test]
    public function strict_mode_fails_when_required_production_configuration_is_missing(): void
    {
        config()->set('app.env', 'testing');
        config()->set('app.debug', true);

        $this->artisan('performance:readiness', ['--strict' => true])
            ->expectsOutputToContain('required production readiness check(s) need action')
            ->assertFailed();
    }
}
