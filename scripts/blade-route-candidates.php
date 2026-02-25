<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$routesPath = $argv[1] ?? $root . '/storage/app/routes-latest.json';
$outputAll = $argv[2] ?? $root . '/storage/app/blade-route-candidates.json';
$outputGet = $argv[3] ?? $root . '/storage/app/blade-ui-get-candidates.json';

$routesRaw = file_get_contents($routesPath);
if ($routesRaw === false) {
    fwrite(STDERR, "Unable to read routes: {$routesPath}\n");
    exit(1);
}

$containsNullBytes = str_contains($routesRaw, "\0");
if ($containsNullBytes) {
    $routesRaw = mb_convert_encoding($routesRaw, 'UTF-8', 'UTF-16LE');
}
$routesRaw = preg_replace('/^\xEF\xBB\xBF/', '', $routesRaw) ?? $routesRaw;
$routes = json_decode($routesRaw, true);
if (! is_array($routes)) {
    fwrite(STDERR, "Invalid routes JSON: {$routesPath}\n");
    exit(1);
}

$actions = [];
$controllerIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/app/Http/Controllers')
);

foreach ($controllerIterator as $fileInfo) {
    if (! $fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
        continue;
    }

    $path = $fileInfo->getPathname();
    $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
    $class = 'App\\Http\\Controllers\\' . str_replace('/', '\\', preg_replace('/^app\/Http\/Controllers\//', '', substr($relative, 0, -4)));

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if (! is_array($lines)) {
        continue;
    }

    $currentMethod = null;
    foreach ($lines as $lineNumber => $line) {
        if (preg_match('/function\s+([A-Za-z0-9_]+)\s*\(/', $line, $matches) === 1) {
            $currentMethod = $matches[1];
            continue;
        }

        if (! $currentMethod) {
            continue;
        }

        if (! str_contains($line, 'return view(')) {
            continue;
        }

        $action = $class . '@' . $currentMethod;
        if (! isset($actions[$action])) {
            $actions[$action] = [];
        }

        $actions[$action][] = [
            'file' => $relative,
            'line' => $lineNumber + 1,
            'code' => trim($line),
        ];
    }
}

$allCandidates = [];
foreach ($routes as $route) {
    if (! is_array($route)) {
        continue;
    }

    $action = (string) ($route['action'] ?? '');
    if (! isset($actions[$action])) {
        continue;
    }

    $method = (string) ($route['method'] ?? '');
    $uri = (string) ($route['uri'] ?? '');
    $name = (string) ($route['name'] ?? '');
    $middleware = $route['middleware'] ?? [];
    $middlewareList = is_array($middleware) ? $middleware : [(string) $middleware];

    foreach ($actions[$action] as $source) {
        $allCandidates[] = [
            'Method' => $method,
            'Uri' => $uri,
            'Name' => $name,
            'Action' => $action,
            'File' => $source['file'],
            'Line' => $source['line'],
            'Code' => $source['code'],
            'Middleware' => $middlewareList,
        ];
    }
}

usort($allCandidates, static fn (array $a, array $b): int => strcmp($a['Uri'], $b['Uri']));
file_put_contents($outputAll, json_encode($allCandidates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$partialNeedles = [
    'partials',
    'task-chat-messages',
    'project-chat-messages',
    'activity-feed',
];

$uiGetCandidates = [];
$seen = [];
foreach ($allCandidates as $row) {
    if (! str_contains((string) $row['Method'], 'GET')) {
        continue;
    }

    $key = $row['Method'] . '|' . $row['Uri'] . '|' . $row['Action'];
    if (isset($seen[$key])) {
        continue;
    }
    $seen[$key] = true;

    $snippet = strtolower((string) $row['Code']);
    $isPartial = false;
    foreach ($partialNeedles as $needle) {
        if (str_contains($snippet, $needle)) {
            $isPartial = true;
            break;
        }
    }

    $uiGetCandidates[] = [
        'Method' => $row['Method'],
        'Uri' => $row['Uri'],
        'Name' => $row['Name'],
        'Action' => $row['Action'],
        'File' => $row['File'],
        'ControllerMethod' => explode('@', (string) $row['Action'])[1] ?? '',
        'PartialOnly' => $isPartial,
        'Snippet' => $row['Code'],
        'Middleware' => $row['Middleware'],
    ];
}

usort($uiGetCandidates, static fn (array $a, array $b): int => strcmp($a['Uri'], $b['Uri']));
file_put_contents($outputGet, json_encode($uiGetCandidates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "all_candidates=" . count($allCandidates) . PHP_EOL;
echo "ui_get_candidates=" . count($uiGetCandidates) . PHP_EOL;
echo "output_all={$outputAll}" . PHP_EOL;
echo "output_get={$outputGet}" . PHP_EOL;
