<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AutomationStatusService;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AutomationStatusController extends Controller
{
    public function __construct(private AutomationStatusService $statusService) {}

    public function index(): InertiaResponse
    {
        $payload = $this->statusService->getStatusPayload();

        return Inertia::render(
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
