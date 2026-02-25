<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$phase = $argv[1] ?? 'phase-baseline';

$commands = [
    'php artisan route:list --json > storage/app/routes-latest.json',
    'php scripts/blade-route-candidates.php storage/app/routes-latest.json storage/app/blade-route-candidates.json storage/app/blade-ui-get-candidates.json',
    sprintf('php scripts/ui-route-classifier.php storage/app/routes-latest.json storage/app/blade-ui-get-candidates.json %s', escapeshellarg($phase)),
    sprintf('php scripts/ui-migration-metrics.php %s storage/app/routes-latest.json storage/app/blade-ui-get-candidates.json storage/app/migration-reports/latest-classification.json', escapeshellarg($phase)),
];

chdir($root);

foreach ($commands as $command) {
    echo "\n$command\n";
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        fwrite(STDERR, "Command failed with exit code {$exitCode}: {$command}\n");
        exit($exitCode);
    }
}

echo "\nPhase reporting complete: {$phase}\n";