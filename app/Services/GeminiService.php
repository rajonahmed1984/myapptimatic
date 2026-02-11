<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GeminiService
{
    public function generateText(string $prompt, array $options = []): string
    {
        if (! config('google_ai.enabled')) {
            throw new \RuntimeException('Google AI is disabled.');
        }

        $apiKey = config('google_ai.api_key');
        if (! $apiKey) {
            throw new \RuntimeException('Missing GOOGLE_AI_API_KEY.');
        }

        $model = $options['model'] ?? config('google_ai.model');
        $baseUrl = rtrim((string) config('google_ai.base_url'), '/');
        $timeout = (int) ($options['timeout'] ?? config('google_ai.timeout'));
        $temperature = (float) ($options['temperature'] ?? config('google_ai.temperature'));
        $maxTokens = (int) ($options['max_output_tokens'] ?? config('google_ai.max_output_tokens'));

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxTokens,
            ],
        ];

        $url = sprintf('%s/models/%s:generateContent?key=%s', $baseUrl, $model, $apiKey);

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);

        return $this->extractText($response);
    }

    private function extractText(Response $response): string
    {
        if (! $response->successful()) {
            $message = $response->json('error.message') ?? $response->body();
            throw new \RuntimeException('Gemini API error: '.$message);
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text) || trim($text) === '') {
            throw new \RuntimeException('Gemini API returned empty response.');
        }

        return trim($text);
    }
}