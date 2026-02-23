<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class HybridUiResponder
{
    public function render(
        Request $request,
        string $feature,
        string $bladeView,
        array $bladeProps,
        string $inertiaComponent,
        array $inertiaProps
    ): View|InertiaResponse {
        if ($this->featureEnabled($request, $feature)) {
            return Inertia::render($inertiaComponent, $inertiaProps);
        }

        return view($bladeView, $bladeProps);
    }

    private function featureEnabled(Request $request, string $feature): bool
    {
        $requestFeature = $request->attributes->get('react_ui_feature');
        if (is_string($requestFeature) && $requestFeature === $feature) {
            return (bool) $request->attributes->get('react_ui_enabled', false);
        }

        return UiFeature::enabled($feature);
    }
}
