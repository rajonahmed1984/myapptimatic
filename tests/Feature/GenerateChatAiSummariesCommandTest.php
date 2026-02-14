<?php

namespace Tests\Feature;

use Tests\TestCase;

class GenerateChatAiSummariesCommandTest extends TestCase
{
    public function test_command_exits_success_when_google_ai_disabled(): void
    {
        config([
            'google_ai.enabled' => false,
        ]);

        $this->artisan('chat:ai-summary --type=project')
            ->assertExitCode(0);
    }
}
