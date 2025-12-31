<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use App\Support\SystemLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class SystemLogController extends Controller
{
    public function index(string $type)
    {
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

        return view('admin.logs.index', [
            'logs' => $logs,
            'logTypes' => $types,
            'activeType' => $type,
            'pageTitle' => $config['label'],
        ]);
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
        $from = $context['from'][0] ?? null;

        if (empty($recipients) || $subject === '' || ($html === '' && $text === '')) {
            return back()->withErrors(['email' => 'Cannot resend this email log.']);
        }

        try {
            Mail::send([], [], function ($message) use ($recipients, $subject, $html, $text, $from) {
                $message->to($recipients)->subject($subject);
                if ($from) {
                    $message->from($from);
                }
                if ($html !== '') {
                    $message->html($html);
                }
                if ($text !== '') {
                    $message->text($text);
                }
            });
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
                'label' => 'Module Log',
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
}
