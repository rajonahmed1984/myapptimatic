<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$message = App\Models\ProjectMessage::find(66);

if (! $message) {
    echo "MSG_NOT_FOUND\n";
    exit(0);
}

echo 'path=' . $message->attachment_path . PHP_EOL;
echo 'project=' . $message->project_id . PHP_EOL;
echo 'isImage=' . ($message->isImageAttachment() ? '1' : '0') . PHP_EOL;
echo 'exists=' . (Illuminate\Support\Facades\Storage::disk('public')->exists($message->attachment_path) ? '1' : '0') . PHP_EOL;
