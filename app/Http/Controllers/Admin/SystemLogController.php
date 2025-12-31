<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;

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
