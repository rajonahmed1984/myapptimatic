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

$inertiaActions = [];
$viewActions = [];
$jsonActions = [];
$downloadActions = [];
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

        if (! $currentMethod) {
            continue;
        }

        if (preg_match('/^\s*\)\s*:\s*([^\{]+)\{?/', $line, $typeMatch) === 1) {
            $returnType = strtolower(trim((string) ($typeMatch[1] ?? '')));
            foreach ($currentAliases as $alias) {
                if (($returnTypeByAction[$alias] ?? '') === '') {
                    $returnTypeByAction[$alias] = $returnType;
                }
            }
        }

        foreach ($currentAliases as $alias) {
            if (str_contains($line, 'Inertia::render(') || str_contains($line, 'inertia(')) {
                $inertiaActions[$alias] = true;
            }
            if (str_contains($line, 'return view(') || str_contains($line, 'response()->view(')) {
                $viewActions[$alias] = true;
            }
            if (str_contains($line, 'response()->json(') || str_contains($line, '->json(')) {
                $jsonActions[$alias] = true;
            }
            if (
                str_contains($line, '->download(')
                || str_contains($line, 'streamDownload(')
                || str_contains($line, "Content-Disposition' => 'inline")
                || str_contains($line, "Content-Disposition' => 'attachment")
            ) {
                $downloadActions[$alias] = true;
            }
            if (preg_match('/\babort\s*\(/', $line) === 1) {
                $abortActions[$alias] = true;
            }
        }

        $primaryAction = $class . '@' . $currentMethod;
        if (preg_match_all('/\$this->([A-Za-z0-9_]+)\s*\(/', $line, $calls) > 0) {
            $calledMethodsByAction[$primaryAction] = $calledMethodsByAction[$primaryAction] ?? [];
            foreach ($calls[1] as $calledMethod) {
                $calledMethodsByAction[$primaryAction][$calledMethod] = true;
            }
        }
    }
}

$propagateByInternalCalls = static function (array $seedActions, array $calledMethodsByAction): array {
    $resolvedActions = $seedActions;
    $changed = true;

    while ($changed) {
        $changed = false;

        foreach ($calledMethodsByAction as $action => $calledMethods) {
            if (isset($resolvedActions[$action])) {
                continue;
            }

            [$className] = explode('@', $action, 2);
            foreach (array_keys($calledMethods) as $calledMethod) {
                $calledAction = $className . '@' . $calledMethod;
                if (isset($resolvedActions[$calledAction])) {
                    $resolvedActions[$action] = true;
                    $changed = true;
                    break;
                }
            }
        }
    }

    return $resolvedActions;
};

$inertiaActions = $propagateByInternalCalls($inertiaActions, $calledMethodsByAction);
$viewActions = $propagateByInternalCalls($viewActions, $calledMethodsByAction);
$jsonActions = $propagateByInternalCalls($jsonActions, $calledMethodsByAction);
$downloadActions = $propagateByInternalCalls($downloadActions, $calledMethodsByAction);

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
    '/_ignition/',
    '_ignition/',
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

$nonUiUriRules = [
    ['regex' => '#/(export|download)(?:/|$)#i', 'reason' => 'non_ui_pattern:download_export'],
    ['regex' => '#/(proof|receipt)(?:/|$)#i', 'reason' => 'non_ui_pattern:download_export'],
    ['regex' => '#/(branding|media)/#i', 'reason' => 'non_ui_pattern:media_asset'],
    ['regex' => '#/user-documents/\{[^/]+\}/\{[^/]+\}/\{[^/]+\}$#i', 'reason' => 'non_ui_pattern:media_asset'],
    ['regex' => '#/chat/(project-messages|task-messages)/\{[^/]+\}/inline$#i', 'reason' => 'non_ui_pattern:chat_inline_attachment'],
    ['regex' => '#/chat/(messages|participants)(?:/|$)#i', 'reason' => 'non_ui_pattern:chat_inline_data'],
    ['regex' => '#/tasks/\{[^/]+\}/activity(?:/items)?(?:/|$)#i', 'reason' => 'non_ui_pattern:chat_inline_data'],
    ['regex' => '#/sync-status(?:/|$)#i', 'reason' => 'non_ui_pattern:status_probe'],
    ['regex' => '#/work-summaries/today(?:/|$)#i', 'reason' => 'non_ui_pattern:status_probe'],
];

$nonUiRouteNameRules = [
    ['regex' => '/\.(download|export|receipt|proof|attachment|inline|messages|participants|presence|stream|upload|sync-status|syncstatus)$/i', 'reason' => 'non_ui_route_name:payload_or_file'],
];

$nonUiMethodRules = [
    ['regex' => '/^(export|export[a-z0-9_]*|download|receipt|proof|attachment|inlineattachment|inline|syncstatus|messages|participants|items|avatar|today|presence|stream|upload)$/i', 'reason' => 'non_ui_method:payload_or_file'],
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
    $normalizedUri = '/' . ltrim($uri, '/');
    $methodName = '';
    if (str_contains($action, '@')) {
        [, $methodName] = explode('@', $action, 2);
    }

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

    if ($classification === 'ambiguous' && $action !== '' && ! isset($inertiaActions[$action]) && ! isset($viewActions[$action])) {
        $returnType = strtolower((string) ($returnTypeByAction[$action] ?? ''));
        if (isset($jsonActions[$action])) {
            $classification = 'non_ui_endpoint';
            $reason = 'non_ui_action:json_payload';
        } elseif (
            str_contains($returnType, 'jsonresponse')
            || str_contains($returnType, 'streamedresponse')
            || str_contains($returnType, 'binaryfileresponse')
        ) {
            $classification = 'non_ui_endpoint';
            $reason = 'non_ui_action:non_html_return_type';
        } elseif (isset($downloadActions[$action])) {
            $classification = 'non_ui_endpoint';
            $reason = 'non_ui_action:file_delivery';
        } elseif (isset($abortActions[$action])) {
            $classification = 'non_ui_endpoint';
            $reason = 'non_ui_action:abort_only';
        }
    }

    if ($classification === 'ambiguous') {
        foreach ($nonUiUriRules as $rule) {
            if (preg_match($rule['regex'], $normalizedUri) === 1) {
                $classification = 'non_ui_endpoint';
                $reason = $rule['reason'];
                break;
            }
        }
    }

    if ($classification === 'ambiguous' && $name !== '') {
        foreach ($nonUiRouteNameRules as $rule) {
            if (preg_match($rule['regex'], $name) === 1) {
                $classification = 'non_ui_endpoint';
                $reason = $rule['reason'];
                break;
            }
        }
    }

    if ($classification === 'ambiguous' && $methodName !== '') {
        foreach ($nonUiMethodRules as $rule) {
            if (preg_match($rule['regex'], $methodName) === 1) {
                $classification = 'non_ui_endpoint';
                $reason = $rule['reason'];
                break;
            }
        }
    }

    if ($classification === 'ambiguous' && isset($partialUris[$uri])) {
        if ($action !== '' && isset($inertiaActions[$action])) {
            $classification = 'ui_page_candidate';
            $reason = 'partial_uri_with_inertia_action';
        } else {
            $classification = 'partial_fragment';
            $reason = 'blade_partial_candidate';
        }
    }

    if ($classification === 'ambiguous' && isset($fullBladeUris[$uri])) {
        $classification = 'ui_page_blade_full';
        $reason = 'blade_full_candidate';
    }

    if (
        $classification === 'ambiguous'
        && $isGetRoute
        && $action !== ''
        && (str_contains($action, '@') || str_starts_with($action, 'App\\Http\\Controllers\\'))
    ) {
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
