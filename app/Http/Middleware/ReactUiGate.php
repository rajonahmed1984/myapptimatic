<?php

namespace App\Http\Middleware;

use App\Support\UiFeature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReactUiGate
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $request->attributes->set('react_ui_feature', $feature);
        $request->attributes->set('react_ui_enabled', UiFeature::enabled($feature));

        return $next($request);
    }
}
