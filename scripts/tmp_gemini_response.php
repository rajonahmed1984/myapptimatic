<?php
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Storage;

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
$responseBody = json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$storagePath = 'tmp/gemini_response.json';
Storage::disk('local')->put($storagePath, $responseBody);
$fullPath = storage_path('app/' . $storagePath);

echo 'status=' . $response->status() . PHP_EOL;
echo 'saved=' . $fullPath . PHP_EOL;
