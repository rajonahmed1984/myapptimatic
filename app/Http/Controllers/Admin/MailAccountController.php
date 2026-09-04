<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\MailAccount;
use App\Models\MailAccountAssignment;
use App\Models\SalesRepresentative;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class MailAccountController extends Controller
{
    public function manage(): InertiaResponse
    {
        $accounts = MailAccount::query()
            ->with(['assignments' => function ($query) {
                $query->orderBy('assignee_type')->orderBy('assignee_id');
            }])
            ->orderBy('email')
            ->get();

        $assignees = [
            'user' => User::query()
                ->whereIn('role', ['master_admin', 'sub_admin', 'admin'])
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user) => [
                    'id' => (int) $user->id,
                    'label' => trim($user->name . ' (' . $user->email . ')'),
                ])
                ->values()
                ->all(),
            'support' => User::query()
                ->where('role', 'support')
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user) => [
                    'id' => (int) $user->id,
                    'label' => trim($user->name . ' (' . $user->email . ')'),
                ])
                ->values()
                ->all(),
            'employee' => Employee::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (Employee $employee) => [
                    'id' => (int) $employee->id,
                    'label' => trim($employee->name . ' (' . (string) $employee->email . ')'),
                ])
                ->values()
                ->all(),
            'sales_rep' => SalesRepresentative::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (SalesRepresentative $rep) => [
                    'id' => (int) $rep->id,
                    'label' => trim($rep->name . ' (' . (string) $rep->email . ')'),
                ])
                ->values()
                ->all(),
        ];

        $serverSettings = [
            'smtp_host' => (string) Setting::getValue('mail_server_smtp_host', config('apptimatic_email.smtp.host', '')),
            'smtp_port' => (int) Setting::getValue('mail_server_smtp_port', config('apptimatic_email.smtp.port', 587)),
            'smtp_encryption' => (string) Setting::getValue('mail_server_smtp_encryption', config('apptimatic_email.smtp.encryption', 'tls')),
            'imap_host' => (string) Setting::getValue('mail_server_imap_host', config('apptimatic_email.imap.host', '')),
            'imap_port' => (int) Setting::getValue('mail_server_imap_port', config('apptimatic_email.imap.port', 993)),
            'imap_encryption' => (string) Setting::getValue('mail_server_imap_encryption', config('apptimatic_email.imap.encryption', 'ssl')),
            'domain' => (string) Setting::getValue('mail_server_domain', ''),
            'auto_provision' => (bool) Setting::getValue('mail_server_auto_provision', true),
            'validate_cert' => (bool) Setting::getValue('mail_server_validate_cert', true),
        ];

        return Inertia::render('Admin/ApptimaticEmail/Manage', [
            'pageTitle' => 'Apptimatic Email Settings',
            'initialAccounts' => $accounts,
            'assignees' => $assignees,
            'serverSettings' => $serverSettings,
            'phpImapEnabled' => function_exists('imap_open'),
            'routes' => [
                'accounts_base' => route('admin.apptimatic-email.accounts.index'),
                'inbox' => route('admin.apptimatic-email.inbox'),
                'manage' => route('admin.apptimatic-email.manage'),
                'settings_update' => route('admin.apptimatic-email.settings.update'),
                'settings_test' => route('admin.apptimatic-email.settings.test'),
            ],
        ]);
    }

    public function index(): JsonResponse
    {
        $accounts = MailAccount::query()
            ->with(['assignments' => function ($query) {
                $query->orderBy('assignee_type')->orderBy('assignee_id');
            }])
            ->orderBy('email')
            ->get();

        return response()->json([
            'data' => $accounts,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->mailboxRules());
        $data['email'] = strtolower((string) $data['email']);

        $mailAccount = MailAccount::query()->create($data);

        return response()->json([
            'message' => 'Mailbox created.',
            'data' => $mailAccount->load('assignments'),
        ], 201);
    }

    public function update(Request $request, MailAccount $mailAccount): JsonResponse
    {
        $data = $request->validate($this->mailboxRules($mailAccount->id));
        $data['email'] = strtolower((string) $data['email']);

        $mailAccount->fill($data)->save();

        return response()->json([
            'message' => 'Mailbox updated.',
            'data' => $mailAccount->load('assignments'),
        ]);
    }

    public function destroy(MailAccount $mailAccount): JsonResponse
    {
        $mailAccount->delete();

        return response()->json([
            'message' => 'Mailbox deleted.',
        ]);
    }

    public function storeAssignment(Request $request, MailAccount $mailAccount): JsonResponse
    {
        $data = $request->validate($this->assignmentRules());
        $this->assertAssigneeExists((string) $data['assignee_type'], (int) $data['assignee_id']);

        $assignment = MailAccountAssignment::query()->updateOrCreate(
            [
                'mail_account_id' => $mailAccount->id,
                'assignee_type' => $data['assignee_type'],
                'assignee_id' => $data['assignee_id'],
            ],
            [
                'can_read' => (bool) $data['can_read'],
                'can_manage' => (bool) $data['can_manage'],
            ]
        );

        return response()->json([
            'message' => 'Mailbox assignment saved.',
            'data' => $assignment,
        ], 201);
    }

    public function updateAssignment(Request $request, MailAccount $mailAccount, MailAccountAssignment $assignment): JsonResponse
    {
        abort_unless($assignment->mail_account_id === $mailAccount->id, 404);

        $data = $request->validate([
            'can_read' => ['required', 'boolean'],
            'can_manage' => ['required', 'boolean'],
        ]);

        $assignment->fill([
            'can_read' => (bool) $data['can_read'],
            'can_manage' => (bool) $data['can_manage'],
        ])->save();

        return response()->json([
            'message' => 'Mailbox assignment updated.',
            'data' => $assignment,
        ]);
    }

    public function destroyAssignment(MailAccount $mailAccount, MailAccountAssignment $assignment): JsonResponse
    {
        abort_unless($assignment->mail_account_id === $mailAccount->id, 404);

        $assignment->delete();

        return response()->json([
            'message' => 'Mailbox assignment deleted.',
        ]);
    }

    private function mailboxRules(?int $mailAccountId = null): array
    {
        return [
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('mail_accounts', 'email')->ignore($mailAccountId),
            ],
            'display_name' => ['nullable', 'string', 'max:255'],
            'imap_host' => ['nullable', 'string', 'max:255'],
            'imap_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'imap_encryption' => ['nullable', Rule::in(['ssl', 'tls', 'none'])],
            'imap_validate_cert' => ['required', 'boolean'],
            'status' => ['required', Rule::in(['active', 'auth_failed', 'disabled'])],
        ];
    }

    private function assignmentRules(): array
    {
        return [
            'assignee_type' => ['required', Rule::in(['user', 'support', 'employee', 'sales_rep'])],
            'assignee_id' => ['required', 'integer', 'min:1'],
            'can_read' => ['required', 'boolean'],
            'can_manage' => ['required', 'boolean'],
        ];
    }

    private function assertAssigneeExists(string $assigneeType, int $assigneeId): void
    {
        $exists = match ($assigneeType) {
            'user' => User::query()->whereKey($assigneeId)->exists(),
            'support' => User::query()->whereKey($assigneeId)->where('role', 'support')->exists(),
            'employee' => Employee::query()->whereKey($assigneeId)->exists(),
            'sales_rep' => SalesRepresentative::query()->whereKey($assigneeId)->exists(),
            default => false,
        };

        abort_if(! $exists, 422, 'Selected assignee does not exist for the chosen type.');
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['nullable', Rule::in(['tls', 'ssl', 'none'])],
            'imap_host' => ['nullable', 'string', 'max:255'],
            'imap_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'imap_encryption' => ['nullable', Rule::in(['ssl', 'tls', 'none'])],
            'domain' => ['nullable', 'string', 'max:255'],
            'auto_provision' => ['required', 'boolean'],
            'validate_cert' => ['required', 'boolean'],
        ]);

        Setting::setValue('mail_server_smtp_host', trim((string) ($data['smtp_host'] ?? '')));
        Setting::setValue('mail_server_smtp_port', (int) ($data['smtp_port'] ?? 587));
        Setting::setValue('mail_server_smtp_encryption', (string) ($data['smtp_encryption'] ?? 'tls'));
        Setting::setValue('mail_server_imap_host', trim((string) ($data['imap_host'] ?? '')));
        Setting::setValue('mail_server_imap_port', (int) ($data['imap_port'] ?? 993));
        Setting::setValue('mail_server_imap_encryption', (string) ($data['imap_encryption'] ?? 'ssl'));
        Setting::setValue('mail_server_domain', strtolower(trim((string) ($data['domain'] ?? ''))));
        Setting::setValue('mail_server_auto_provision', $data['auto_provision'] ? '1' : '0');
        Setting::setValue('mail_server_validate_cert', $data['validate_cert'] ? '1' : '0');
        Setting::flushCache();

        return response()->json([
            'message' => 'Mail server & SMTP settings saved successfully.',
            'settings' => [
                'smtp_host' => Setting::getValue('mail_server_smtp_host'),
                'smtp_port' => (int) Setting::getValue('mail_server_smtp_port', 587),
                'smtp_encryption' => Setting::getValue('mail_server_smtp_encryption', 'tls'),
                'imap_host' => Setting::getValue('mail_server_imap_host'),
                'imap_port' => (int) Setting::getValue('mail_server_imap_port', 993),
                'imap_encryption' => Setting::getValue('mail_server_imap_encryption', 'ssl'),
                'domain' => Setting::getValue('mail_server_domain', ''),
                'auto_provision' => (bool) Setting::getValue('mail_server_auto_provision', true),
                'validate_cert' => (bool) Setting::getValue('mail_server_validate_cert', true),
            ],
        ]);
    }

    public function testConnection(Request $request): JsonResponse
    {
        $smtpHost = trim((string) $request->input('smtp_host', Setting::getValue('mail_server_smtp_host', '')));
        $smtpPort = (int) $request->input('smtp_port', Setting::getValue('mail_server_smtp_port', 587));
        $imapHost = trim((string) $request->input('imap_host', Setting::getValue('mail_server_imap_host', $smtpHost)));
        $imapPort = (int) $request->input('imap_port', Setting::getValue('mail_server_imap_port', 993));
        $imapEncryption = (string) $request->input('imap_encryption', Setting::getValue('mail_server_imap_encryption', 'ssl'));

        $results = [
            'smtp' => ['success' => false, 'message' => ''],
            'imap' => ['success' => false, 'message' => ''],
        ];

        if ($smtpHost !== '' && $smtpPort > 0) {
            $errno = 0;
            $errstr = '';
            $conn = @stream_socket_client("tcp://{$smtpHost}:{$smtpPort}", $errno, $errstr, 4);
            if ($conn) {
                $banner = fgets($conn, 512);
                fclose($conn);
                $bannerText = trim((string) $banner);
                $results['smtp'] = [
                    'success' => true,
                    'message' => 'SMTP server reachable on port ' . $smtpPort . ($bannerText !== '' ? ' (' . substr($bannerText, 0, 80) . ')' : '.'),
                ];
            } else {
                $results['smtp'] = [
                    'success' => false,
                    'message' => "Could not reach SMTP ({$smtpHost}:{$smtpPort}): {$errstr} ({$errno})",
                ];
            }
        } else {
            $results['smtp'] = [
                'success' => false,
                'message' => 'SMTP host or port is missing.',
            ];
        }

        if ($imapHost !== '' && $imapPort > 0) {
            $errno = 0;
            $errstr = '';
            $prefix = ($imapEncryption === 'ssl') ? 'ssl://' : 'tcp://';
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $conn = @stream_socket_client("{$prefix}{$imapHost}:{$imapPort}", $errno, $errstr, 4, STREAM_CLIENT_CONNECT, $context);
            if ($conn) {
                $banner = fgets($conn, 512);
                fclose($conn);
                $bannerText = trim((string) $banner);
                $results['imap'] = [
                    'success' => true,
                    'message' => 'IMAP server reachable on port ' . $imapPort . ($bannerText !== '' ? ' (' . substr($bannerText, 0, 80) . ')' : '.'),
                ];
            } else {
                $results['imap'] = [
                    'success' => false,
                    'message' => "Could not reach IMAP ({$imapHost}:{$imapPort}): {$errstr} ({$errno})",
                ];
            }
        } else {
            $results['imap'] = [
                'success' => false,
                'message' => 'IMAP host or port is missing.',
            ];
        }

        $allSuccess = $results['smtp']['success'] && $results['imap']['success'];

        return response()->json([
            'success' => $allSuccess,
            'results' => $results,
            'message' => $allSuccess
                ? 'Connection test successful for both SMTP and IMAP!'
                : 'Connection test completed with warnings or unreachable services.',
        ]);
    }
}
