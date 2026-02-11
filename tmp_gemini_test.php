<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$svc = $app->make(App\Services\GeminiService::class);
try {
    $result = $svc->generateText('Respond with the single word: ok');
    echo $result;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage());
    exit(1);
}