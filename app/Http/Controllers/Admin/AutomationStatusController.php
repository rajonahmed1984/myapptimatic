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
        return [
            'pageTitle' => 'Automation Status',
            'statusLabel' => (string) ($payload['statusLabel'] ?? 'Pending'),
            'statusClasses' => (string) ($payload['statusClasses'] ?? 'bg-slate-100 text-slate-600'),
            'lastStatus' => $payload['lastStatus'] ?? null,
            'lastError' => $payload['lastError'] ?? null,
            'lastInvocationText' => (string) ($payload['lastInvocationText'] ?? 'Never'),
            'lastInvocationAt' => (string) ($payload['lastInvocationAt'] ?? 'Not yet invoked'),
            'lastCompletionText' => (string) ($payload['lastCompletionText'] ?? 'Never'),
            'lastCompletionAt' => (string) ($payload['lastCompletionAt'] ?? 'Not yet completed'),
            'nextDailyRunText' => (string) ($payload['nextDailyRunText'] ?? 'Not scheduled'),
            'nextDailyRunAt' => (string) ($payload['nextDailyRunAt'] ?? 'No historical run'),
            'portalTimeZone' => (string) ($payload['portalTimeZone'] ?? 'UTC'),
            'portalTimeLabel' => (string) ($payload['portalTimeLabel'] ?? '--'),
            'cronSetup' => (bool) ($payload['cronSetup'] ?? false),
            'cronInvoked' => (bool) ($payload['cronInvoked'] ?? false),
            'dailyCronRun' => (bool) ($payload['dailyCronRun'] ?? false),
            'dailyCronCompleting' => (bool) ($payload['dailyCronCompleting'] ?? false),
            'cronInvocationWindowHours' => (int) ($payload['cronInvocationWindowHours'] ?? 24),
            'dailyCronWindowHours' => (int) ($payload['dailyCronWindowHours'] ?? 24),
            'aiHealth' => [
                'enabled' => (bool) data_get($payload, 'aiHealth.enabled', false),
                'risk_enabled' => (bool) data_get($payload, 'aiHealth.risk_enabled', false),
                'queue_pending' => (int) data_get($payload, 'aiHealth.queue_pending', 0),
                'queue_failed' => (int) data_get($payload, 'aiHealth.queue_failed', 0),
                'status_label' => (string) data_get($payload, 'aiHealth.status_label', 'Unknown'),
                'status_classes' => (string) data_get($payload, 'aiHealth.status_classes', 'bg-slate-100 text-slate-600'),
            ],
            'dailyActions' => $payload['dailyActions'] ?? [],
            'routes' => [
                'cron_settings' => route('admin.settings.edit', ['tab' => 'automation']),
            ],
        ];
    }
}
