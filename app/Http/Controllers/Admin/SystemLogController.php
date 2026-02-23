<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MailCategory;
use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use App\Services\Mail\MailSender;
use App\Support\HybridUiResponder;
use App\Support\SystemLogger;
use App\Support\UiFeature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Inertia\Response as InertiaResponse;

class SystemLogController extends Controller
{
    public function index(
        Request $request,
        string $type,
        HybridUiResponder $hybridUiResponder
    ): View|InertiaResponse {
        $types = $this->logTypes();

        if (! isset($types[$type])) {
            abort(404);
        }

        $config = $types[$type];
        $logs = SystemLog::query()
            ->with('user')
            ->where('category', $config['category'])
            ->latest()
            ->paginate(50);

        $payload = [
            'logs' => $logs,
            'logTypes' => $types,
            'activeType' => $type,
            'pageTitle' => $config['label'],
        ];

        return $hybridUiResponder->render(
            $request,
            UiFeature::ADMIN_LOGS_INDEX,
            'admin.logs.index',
            $payload,
            'Admin/Logs/Index',
            $this->indexInertiaProps($logs, $types, $type, $config['label'])
        );
    }

    public function resend(SystemLog $systemLog): RedirectResponse
    {
        if ($systemLog->category !== 'email') {
            abort(404);
        }

        $context = $systemLog->context ?? [];
        $recipients = $context['to'] ?? [];
        $subject = (string) ($context['subject'] ?? '');
        $html = (string) ($context['html'] ?? '');
        $text = (string) ($context['text'] ?? '');

        if (empty($recipients) || $subject === '' || ($html === '' && $text === '')) {
            return back()->withErrors(['email' => 'Cannot resend this email log.']);
        }

        try {
            app(MailSender::class)->sendHtmlText(
                MailCategory::SYSTEM,
                $recipients,
                $subject,
                $html !== '' ? $html : null,
                $text !== '' ? $text : null
            );
        } catch (\Throwable $e) {
            return back()->withErrors(['email' => 'Resend failed: '.$e->getMessage()]);
        }

        SystemLogger::write('email', 'Email resent.', [
            'subject' => $subject,
            'to' => $recipients,
        ]);

        return back()->with('status', 'Email resent.');
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
        string $pageTitle
    ): array {
        $dateFormat = config('app.date_format', 'd-m-Y');

        return [
            'pageTitle' => $pageTitle,
            'activeType' => $activeType,
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
                    'created_at_display' => $log->created_at?->format($dateFormat.' H:i') ?? '--',
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
