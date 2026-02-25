<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$phaseLabel = $argv[1] ?? 'phase-baseline';
$routesPath = $argv[2] ?? $root . '/storage/app/routes-latest.json';
$bladeCandidatesPath = $argv[3] ?? $root . '/storage/app/blade-ui-get-candidates.json';
$classificationPath = $argv[4] ?? $root . '/storage/app/migration-reports/latest-classification.json';
$outputDir = $root . '/storage/app/migration-reports';

if (! is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$routesRaw = file_get_contents($routesPath);
if ($routesRaw === false) {
    fwrite(STDERR, "Unable to read routes file: {$routesPath}\n");
    exit(1);
}

if (str_contains($routesRaw, "\0")) {
    $routesRaw = mb_convert_encoding($routesRaw, 'UTF-8', 'UTF-16LE');
}
$routesRaw = preg_replace('/^\xEF\xBB\xBF/', '', $routesRaw) ?? $routesRaw;
$routes = json_decode($routesRaw, true);
if (! is_array($routes)) {
    fwrite(STDERR, "Invalid routes JSON: {$routesPath}\n");
    exit(1);
}

$classificationRaw = is_file($classificationPath) ? file_get_contents($classificationPath) : false;
if (is_string($classificationRaw)) {
    if (str_contains($classificationRaw, "\0")) {
        $classificationRaw = mb_convert_encoding($classificationRaw, 'UTF-8', 'UTF-16LE');
    }
    $classificationRaw = preg_replace('/^\xEF\xBB\xBF/', '', $classificationRaw) ?? $classificationRaw;
}
$classification = is_string($classificationRaw) ? json_decode($classificationRaw, true) : null;
$classifiedRoutes = is_array($classification['routes'] ?? null) ? $classification['routes'] : [];

$bladeCandidatesRaw = is_file($bladeCandidatesPath) ? file_get_contents($bladeCandidatesPath) : false;
if (is_string($bladeCandidatesRaw)) {
    if (str_contains($bladeCandidatesRaw, "\0")) {
        $bladeCandidatesRaw = mb_convert_encoding($bladeCandidatesRaw, 'UTF-8', 'UTF-16LE');
    }
    $bladeCandidatesRaw = preg_replace('/^\xEF\xBB\xBF/', '', $bladeCandidatesRaw) ?? $bladeCandidatesRaw;
}
$bladeCandidates = is_string($bladeCandidatesRaw) ? json_decode($bladeCandidatesRaw, true) : [];
if (! is_array($bladeCandidates)) {
    $bladeCandidates = [];
}

$fullBladeActions = [];
$partialBlade = [];
foreach ($bladeCandidates as $row) {
    if (! is_array($row)) {
        continue;
    }

    $action = (string) ($row['Action'] ?? '');
    $uri = (string) ($row['Uri'] ?? '');
    $partialOnly = (bool) ($row['PartialOnly'] ?? false);

    if ($partialOnly) {
        if (empty($classifiedRoutes)) {
            $partialBlade[] = [
                'uri' => $uri,
                'name' => (string) ($row['Name'] ?? ''),
                'action' => $action,
            ];
        }
    } else {
        $fullBladeActions[$action] = true;
    }
}

if (! empty($classifiedRoutes)) {
    $partialBlade = [];
    foreach ($classifiedRoutes as $row) {
        if (! is_array($row)) {
            continue;
        }

        if ((string) ($row['classification'] ?? '') !== 'partial_fragment') {
            continue;
        }

        $partialBlade[] = [
            'uri' => (string) ($row['uri'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'action' => (string) ($row['action'] ?? ''),
        ];
    }
}

$inertiaActions = [];
$directInertiaActions = [];
$viewActions = [];
$jsonActions = [];
$abortActions = [];
$returnTypeByAction = [];
$calledMethodsByAction = [];
$controllerFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/app/Http/Controllers')
);

foreach ($controllerFiles as $fileInfo) {
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
    $currentAliases = [];
    foreach ($lines as $line) {
        if (preg_match('/function\s+([A-Za-z0-9_]+)\s*\(/', $line, $matches) === 1) {
            $currentMethod = $matches[1];
            $action = $class . '@' . $currentMethod;
            $currentAliases = [$action];
            if ($currentMethod === '__invoke') {
                $currentAliases[] = $class;
            }

            if (preg_match('/\)\s*:\s*([^\{]+)\{?/', $line, $typeMatch) === 1) {
                $returnType = strtolower(trim((string) ($typeMatch[1] ?? '')));
                foreach ($currentAliases as $alias) {
                    $returnTypeByAction[$alias] = $returnType;
                }
            } else {
                foreach ($currentAliases as $alias) {
                    if (! isset($returnTypeByAction[$alias])) {
                        $returnTypeByAction[$alias] = '';
                    }
                }
            }

            continue;
        }

        if ($currentMethod) {
            if (preg_match('/^\s*\)\s*:\s*([^\{]+)\{?/', $line, $typeMatch) === 1) {
                $returnType = strtolower(trim((string) ($typeMatch[1] ?? '')));
                foreach ($currentAliases as $alias) {
                    if (($returnTypeByAction[$alias] ?? '') === '') {
                        $returnTypeByAction[$alias] = $returnType;
                    }
                }
            }
        }

        if (! $currentMethod) {
            continue;
        }

        foreach ($currentAliases as $alias) {
            if (str_contains($line, 'Inertia::render(') || str_contains($line, 'inertia(')) {
                $directInertiaActions[$alias] = true;
            }
            if (str_contains($line, 'response()->json(') || str_contains($line, '->json(')) {
                $jsonActions[$alias] = true;
            }
            if (preg_match('/\babort\s*\(/', $line) === 1) {
                $abortActions[$alias] = true;
            }
            if (str_contains($line, 'return view(')) {
                $viewActions[$alias] = true;
            }
        }

        $primaryAction = $class . '@' . $currentMethod;
        if (preg_match_all('/\$this->([A-Za-z0-9_]+)\s*\(/', $line, $calls) === 1) {
            $calledMethodsByAction[$primaryAction] = $calledMethodsByAction[$primaryAction] ?? [];
            foreach ($calls[1] as $calledMethod) {
                $calledMethodsByAction[$primaryAction][$calledMethod] = true;
            }
        }
    }
}

$inertiaActions = $directInertiaActions;
$changed = true;
while ($changed) {
    $changed = false;

    foreach ($calledMethodsByAction as $action => $calledMethods) {
        if (isset($inertiaActions[$action])) {
            continue;
        }

        [$className] = explode('@', $action, 2);
        foreach (array_keys($calledMethods) as $calledMethod) {
            $calledAction = $className . '@' . $calledMethod;
            if (isset($inertiaActions[$calledAction])) {
                $inertiaActions[$action] = true;
                $changed = true;
                break;
            }
        }
    }
}

$uiRoutes = [];
if (! empty($classifiedRoutes)) {
    foreach ($classifiedRoutes as $row) {
        if (! is_array($row)) {
            continue;
        }
        if (! str_starts_with((string) ($row['classification'] ?? ''), 'ui_page_')) {
            continue;
        }
        $uiRoutes[] = $row;
    }
} else {
    foreach ($routes as $route) {
        if (! is_array($route)) {
            continue;
        }
        $method = (string) ($route['method'] ?? '');
        if (! str_contains($method, 'GET')) {
            continue;
        }
        $uiRoutes[] = $route;
    }
}

$convertedUi = 0;
$remainingBladeUi = [];
$notDetectedUi = [];

foreach ($uiRoutes as $route) {
    $action = (string) ($route['action'] ?? '');
    $uri = (string) ($route['uri'] ?? '');
    $name = (string) ($route['name'] ?? '');

    if (isset($fullBladeActions[$action])) {
        $remainingBladeUi[] = [
            'uri' => $uri,
            'name' => $name,
            'action' => $action,
        ];
        continue;
    }

    if (isset($inertiaActions[$action])) {
        $convertedUi++;
        continue;
    }

    if (isset($viewActions[$action])) {
        $remainingBladeUi[] = [
            'uri' => $uri,
            'name' => $name,
            'action' => $action,
        ];
        continue;
    }

    $notDetectedUi[] = [
        'uri' => $uri,
        'name' => $name,
        'action' => $action,
    ];
}

$wrapperRoutes = 0;
$wrapperRouteDetails = [];
$classificationByRoute = [];
foreach ($classifiedRoutes as $row) {
    if (! is_array($row)) {
        continue;
    }

    $key = sprintf(
        '%s|%s|%s|%s',
        (string) ($row['method'] ?? ''),
        (string) ($row['uri'] ?? ''),
        (string) ($row['name'] ?? ''),
        (string) ($row['action'] ?? '')
    );

    $classificationByRoute[$key] = (string) ($row['classification'] ?? '');
}

foreach ($routes as $route) {
    if (! is_array($route)) {
        continue;
    }

    $method = (string) ($route['method'] ?? '');
    $uri = (string) ($route['uri'] ?? '');
    $name = (string) ($route['name'] ?? '');
    $action = (string) ($route['action'] ?? '');

    $middleware = $route['middleware'] ?? [];
    $list = is_array($middleware) ? $middleware : [(string) $middleware];
    $usesWrapper = false;
    foreach ($list as $value) {
        if (str_contains((string) $value, 'ConvertAdminViewToInertia')) {
            $usesWrapper = true;
            break;
        }
    }

    if (! $usesWrapper) {
        continue;
    }

    $routeKey = sprintf('%s|%s|%s|%s', $method, $uri, $name, $action);
    $classification = $classificationByRoute[$routeKey] ?? '';

    if ($classification !== '' && ! in_array($classification, ['ui_page_candidate', 'ui_page_blade_full', 'partial_fragment'], true)) {
        continue;
    }

    if ($classification === '' && ! str_contains($method, 'GET')) {
        continue;
    }

    if ($action !== '' && isset($inertiaActions[$action])) {
        continue;
    }

    if ($action !== '' && isset($jsonActions[$action])) {
        continue;
    }

    if ($action !== '' && isset($abortActions[$action]) && ! isset($inertiaActions[$action])) {
        continue;
    }

    $returnType = strtolower((string) ($returnTypeByAction[$action] ?? ''));
    if ($returnType !== '') {
        if (str_contains($returnType, 'jsonresponse') || str_contains($returnType, 'streamedresponse')) {
            continue;
        }
    }

    $methodName = '';
    if ($action !== '' && str_contains($action, '@')) {
        [, $methodName] = explode('@', $action, 2);
    }

    if ($methodName !== '' && preg_match('/(export|download|receipt|proof|attachment|inline|messages|participants|items|stream|presence|syncstatus)$/i', $methodName) === 1) {
        continue;
    }

    $wrapperRoutes++;
    $wrapperRouteDetails[] = [
        'method' => $method,
        'uri' => $uri,
        'name' => $name,
        'action' => $action,
        'classification' => $classification,
    ];
}

$summary = [
    'total_routes' => count($routes),
    'total_ui_page_get_routes' => count($uiRoutes),
    'converted_ui_pages' => $convertedUi,
    'remaining_full_blade_ui_pages' => count($remainingBladeUi),
    'remaining_partial_blade_fragments' => count($partialBlade),
    'wrapper_dependent_routes' => $wrapperRoutes,
    'ui_not_detected' => count($notDetectedUi),
];

$report = [
    'generated_at' => date(DATE_ATOM),
    'phase' => $phaseLabel,
    'inputs' => [
        'routes' => $routesPath,
        'blade_candidates' => $bladeCandidatesPath,
        'classification' => $classificationPath,
    ],
    'summary' => $summary,
    'remaining_full_blade_ui_pages' => $remainingBladeUi,
    'remaining_partial_blade_fragments' => $partialBlade,
    'wrapper_dependent_route_details' => $wrapperRouteDetails,
    'ui_not_detected' => $notDetectedUi,
];

$outputPath = $outputDir . '/' . date('Y-m-d') . '-' . $phaseLabel . '.json';
file_put_contents($outputPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
file_put_contents($outputDir . '/latest-ui-metrics.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "output={$outputPath}\n";
foreach ($summary as $key => $value) {
    echo "{$key}={$value}\n";
}
