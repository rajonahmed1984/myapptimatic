<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AutomationStatusService;
use App\Support\HybridUiResponder;
use App\Support\UiFeature;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Response as InertiaResponse;

class AutomationStatusController extends Controller
{
    public function __construct(private AutomationStatusService $statusService) {}

    public function index(
        Request $request,
        HybridUiResponder $hybridUiResponder
    ): View|InertiaResponse {
        $payload = $this->statusService->getStatusPayload();

        return $hybridUiResponder->render(
            $request,
            UiFeature::ADMIN_AUTOMATION_STATUS_INDEX,
            'admin.automation-status',
            $payload,
            'Admin/AutomationStatus/Index',
            $this->indexInertiaProps($payload)
        );
    }

    private function indexInertiaProps(array $payload): array
    {
        return array_merge($payload, [
            'pageTitle' => 'Automation Status',
            'routes' => [
                'cron_settings' => route('admin.settings.edit', ['tab' => 'cron']),
            ],
        ]);
    }
}
