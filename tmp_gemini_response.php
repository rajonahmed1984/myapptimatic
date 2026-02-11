<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$apiKey = config('google_ai.api_key');
$baseUrl = rtrim((string) config('google_ai.base_url'), '/');
$model = config('google_ai.model');
$prompt = 'Output strict JSON with keys summary, sentiment, priority, reply_draft, action_items. Summary in Bengali.';

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
        'temperature' => 0.4,
        'maxOutputTokens' => 600,
    ],
];

$url = sprintf('%s/models/%s:generateContent?key=%s', $baseUrl, $model, $apiKey);
$response = Illuminate\Support\Facades\Http::acceptJson()->asJson()->post($url, $payload);
file_put_contents(__DIR__ . '/tmp_gemini_response.json', json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo 'status=' . $response->status() . PHP_EOL;