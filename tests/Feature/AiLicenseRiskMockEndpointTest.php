<?php

namespace Tests\Feature;

use Tests\TestCase;

class AiLicenseRiskMockEndpointTest extends TestCase
{
    public function test_mock_ai_license_risk_health_endpoint_is_available_for_get(): void
    {
        $response = $this->getJson('/v1/license-risk');

        $response->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_mock_ai_license_risk_endpoint_returns_mock_payload(): void
    {
        config([
            'ai.token' => '',
            'ai.hmac_secret' => '',
        ]);

        $response = $this->postJson('/v1/license-risk', [
            'decision' => 'allow',
            'reason' => 'ok',
            'domain' => 'example.com',
            'request_id' => 'req-1',
        ]);

        $response->assertOk()
            ->assertJsonPath('decision', 'allow')
            ->assertJsonPath('details.mock', true);
    }

    public function test_mock_ai_license_risk_endpoint_validates_token_and_signature_when_configured(): void
    {
        config([
            'ai.token' => 'test-token',
            'ai.hmac_secret' => 'test-secret',
        ]);

        $body = json_encode([
            'decision' => 'allow',
            'reason' => 'ok',
            'domain' => 'example.com',
        ], JSON_UNESCAPED_UNICODE);

        $timestamp = (string) now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'test-secret');

        $response = $this->call(
            'POST',
            '/v1/license-risk',
            [],
            [],
            [],
            [
                'HTTP_Authorization' => 'Bearer test-token',
                'HTTP_X-Timestamp' => $timestamp,
                'HTTP_X-Signature' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $body
        );

        $response->assertOk()
            ->assertJsonPath('details.mock', true);
    }
}
