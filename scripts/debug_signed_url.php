<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo Illuminate\Support\Facades\URL::signedRoute('chat.project-messages.inline', ['message' => 66], null, false) . PHP_EOL;

echo 'exists=' . (Illuminate\Support\Facades\Storage::disk('public')->exists('project-messages/12/0c41b2ed-7e08-4741-addc-19f4d1fefcd5-UrNyViiA.jpg') ? '1' : '0') . PHP_EOL;
