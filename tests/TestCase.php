<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake([
            '*127.0.0.1:8000/v1/license-risk*' => Http::response([
                'risk_score' => 0.0,
                'decision' => 'allow',
                'reason' => 'testing_fake',
                'details' => ['mock' => true],
            ], 200),
        ]);

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }
}
