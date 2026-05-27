<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ResendSystemLogEmailJob;
use App\Models\SystemLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

use Illuminate\Support\Facades\Cache;

class SystemLogController extends Controller
{
    public function index(Request $request, string $type): InertiaResponse
    {
        $types = $this->logTypes();

        if (! isset($types[$type])) {
            abort(404);
        }

        $config = $types[$type];
        $search = trim((string) $request->query('search', ''));
        $logs = SystemLog::query()
            ->with('user:id,name')
            ->forCategory($config['category'])
            ->search($search)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render(
            'Admin/Logs/Index',
            $this->indexInertiaProps($logs, $types, $type, $config['label'], $search)
        );
    }

    public function resend(Request $request, SystemLog $systemLog): RedirectResponse
    {
        if ($systemLog->category !== 'email') {
            abort(404);
        }

        $userId = (int) ($request->user()?->id ?? 0);
        $window = (int) floor(now()->timestamp / 30);
        $idempotencyKey = sprintf('email_resend:%d:%d:%d', (int) $systemLog->id, $userId, $window);
        if (! Cache::add($idempotencyKey, true, now()->addSeconds(45))) {
            return back()->withErrors(['email' => 'Duplicate resend blocked. Please wait a moment.']);
        }

        ResendSystemLogEmailJob::dispatch(
            systemLogId: (int) $systemLog->id,
            requestedBy: $userId,
            requestIp: (string) $request->ip(),
            idempotencyWindow: $window,
        )->onQueue('default');

        return back()->with('status', 'Email resend queued.');
    }

    public function destroy(SystemLog $systemLog): RedirectResponse
    {
        if ($systemLog->category !== 'email') {
            abort(404);
        }

        $systemLog->delete();

        return back()->with('status', 'Email log deleted.');
    }

    private function logTypes(): array
    {
        return [
            'activity' => [
                'label' => 'Activity Log',
                'category' => 'activity',
                'route' => 'admin.logs.activity',
            ],
            'admin' => [
                'label' => 'Admin Log',
                'category' => 'admin',
                'route' => 'admin.logs.admin',
            ],
            'module' => [
                'label' => 'Payment Gateways Log',
                'category' => 'module',
                'route' => 'admin.logs.module',
            ],
            'email' => [
                'label' => 'Email Message Log',
                'category' => 'email',
                'route' => 'admin.logs.email',
            ],
            'ticket-mail-import' => [
                'label' => 'Ticket Mail Import Log',
                'category' => 'ticket_mail_import',
                'route' => 'admin.logs.ticket-mail-import',
            ],
        ];
    }

    private function indexInertiaProps(
        LengthAwarePaginator $logs,
        array $types,
        string $activeType,
        string $pageTitle,
        string $search
    ): array {
        $dateFormat = config('app.date_format', 'd-m-Y');

        return [
            'pageTitle' => $pageTitle,
            'activeType' => $activeType,
            'filters' => [
                'search' => $search,
            ],
            'routes' => [
                'current' => url()->current(),
            ],
            'logTypes' => collect($types)->map(function (array $type, string $slug) use ($activeType) {
                return [
                    'slug' => $slug,
                    'label' => $type['label'],
                    'href' => route($type['route']),
                    'active' => $slug === $activeType,
                ];
            })->values()->all(),
            'logs' => collect($logs->items())->map(function (SystemLog $log) use ($dateFormat) {
                $level = strtolower((string) $log->level);

                return [
                    'id' => $log->id,
                    'created_at_display' => $log->created_at?->format(config('app.datetime_format', 'd-m-Y h:i A')) ?? '--',
                    'user_name' => $log->user?->name ?? 'System',
                    'ip_address' => $log->ip_address ?? '--',
                    'level' => $level,
                    'level_label' => strtoupper($level),
                    'message' => (string) $log->message,
                    'context_json' => ! empty($log->context) ? json_encode($log->context) : null,
                ];
            })->values()->all(),
            'pagination' => [
                'count' => $logs->count(),
                'total' => $logs->total(),
                'has_pages' => $logs->hasPages(),
                'previous_url' => $logs->previousPageUrl(),
                'next_url' => $logs->nextPageUrl(),
            ],
        ];
    }
}
