<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\MailAccount;
use App\Models\MailAccountAssignment;
use App\Models\SalesRepresentative;
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

        return Inertia::render('Admin/ApptimaticEmail/Manage', [
            'pageTitle' => 'Apptimatic Email Settings',
            'initialAccounts' => $accounts,
            'assignees' => $assignees,
            'routes' => [
                'accounts_base' => route('admin.apptimatic-email.accounts.index'),
                'inbox' => route('admin.apptimatic-email.inbox'),
                'manage' => route('admin.apptimatic-email.manage'),
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
}
