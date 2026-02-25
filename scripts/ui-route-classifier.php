<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$routesPath = $argv[1] ?? $root . '/storage/app/routes-latest.json';
$bladeCandidatesPath = $argv[2] ?? $root . '/storage/app/blade-ui-get-candidates.json';
$phaseLabel = $argv[3] ?? 'phase-baseline';
$outputDir = $root . '/storage/app/migration-reports';

if (! is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$routesRaw = file_get_contents($routesPath);
if ($routesRaw === false) {
    fwrite(STDERR, "Unable to read routes file: {$routesPath}\n");
    exit(1);
}

$containsNullBytes = str_contains($routesRaw, "\0");
if ($containsNullBytes) {
    $routesRaw = mb_convert_encoding($routesRaw, 'UTF-8', 'UTF-16LE');
}
$routesRaw = preg_replace('/^\xEF\xBB\xBF/', '', $routesRaw) ?? $routesRaw;
$routes = json_decode($routesRaw, true);
if (! is_array($routes)) {
    fwrite(STDERR, "Invalid JSON in routes file: {$routesPath}\n");
    exit(1);
}

$partialUris = [];
$fullBladeUris = [];
if (is_file($bladeCandidatesPath)) {
    $candidateRaw = file_get_contents($bladeCandidatesPath);
    if ($candidateRaw !== false) {
        if (str_contains($candidateRaw, "\0")) {
            $candidateRaw = mb_convert_encoding($candidateRaw, 'UTF-8', 'UTF-16LE');
        }
        $candidateRaw = preg_replace('/^\xEF\xBB\xBF/', '', $candidateRaw) ?? $candidateRaw;
        $candidates = json_decode($candidateRaw, true);
        if (is_array($candidates)) {
            foreach ($candidates as $row) {
                if (! is_array($row) || ! isset($row['Uri'])) {
                    continue;
                }

                $uri = (string) $row['Uri'];
                $isPartial = (bool) ($row['PartialOnly'] ?? false);
                if ($isPartial) {
                    $partialUris[$uri] = true;
                } else {
                    $fullBladeUris[$uri] = true;
                }
            }
        }
    }
}

$summary = [
    'total_routes' => count($routes),
    'ui_page_get_routes' => 0,
    'ui_page_blade_full' => 0,
    'ui_page_candidate' => 0,
    'partial_fragment' => 0,
    'non_ui_endpoint' => 0,
    'ambiguous' => 0,
];

$classified = [];

$nonUiNeedles = [
    '/payments/',
    'payments/',
    '/callback',
    '/webhook',
    '/upload',
    '/stream',
    '/download',
    '/attachment',
    'attachment',
    '/cron/',
    'cron/',
];

foreach ($routes as $route) {
    if (! is_array($route)) {
        continue;
    }

    $method = (string) ($route['method'] ?? '');
    $uri = (string) ($route['uri'] ?? '');
    $action = (string) ($route['action'] ?? '');
    $name = (string) ($route['name'] ?? '');
    $middleware = $route['middleware'] ?? [];
    $middlewareList = is_array($middleware) ? $middleware : [(string) $middleware];

    $verbs = array_filter(array_map('trim', explode('|', $method)));
    $isGetRoute = in_array('GET', $verbs, true) || in_array('HEAD', $verbs, true);

    $classification = 'ambiguous';
    $reason = 'not_detected';

    if (! $isGetRoute) {
        $classification = 'non_ui_endpoint';
        $reason = 'non_get_method';
    } elseif ($action === 'Closure' || str_contains($action, 'RedirectController')) {
        $classification = 'non_ui_endpoint';
        $reason = 'closure_or_redirect';
    } else {
        foreach ($nonUiNeedles as $needle) {
            if (str_contains($uri, $needle)) {
                $classification = 'non_ui_endpoint';
                $reason = "non_ui_pattern:{$needle}";
                break;
            }
        }
    }

    if ($classification === 'ambiguous' && isset($partialUris[$uri])) {
        $classification = 'partial_fragment';
        $reason = 'blade_partial_candidate';
    }

    if ($classification === 'ambiguous' && isset($fullBladeUris[$uri])) {
        $classification = 'ui_page_blade_full';
        $reason = 'blade_full_candidate';
    }

    if ($classification === 'ambiguous' && $isGetRoute && str_contains($action, '@')) {
        $classification = 'ui_page_candidate';
        $reason = 'controller_get_candidate';
    }

    $summary[$classification] = ($summary[$classification] ?? 0) + 1;
    if (str_starts_with($classification, 'ui_page_')) {
        $summary['ui_page_get_routes']++;
    }

    $classified[] = [
        'method' => $method,
        'uri' => $uri,
        'name' => $name,
        'action' => $action,
        'middleware' => $middlewareList,
        'classification' => $classification,
        'reason' => $reason,
    ];
}

$report = [
    'generated_at' => date(DATE_ATOM),
    'phase' => $phaseLabel,
    'inputs' => [
        'routes' => $routesPath,
        'blade_candidates' => $bladeCandidatesPath,
    ],
    'summary' => $summary,
    'routes' => $classified,
];

$outputPath = $outputDir . '/' . date('Y-m-d') . '-' . $phaseLabel . '-classification.json';
file_put_contents($outputPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
file_put_contents($outputDir . '/latest-classification.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "output={$outputPath}\n";
foreach ($summary as $key => $value) {
    echo "{$key}={$value}\n";
}
