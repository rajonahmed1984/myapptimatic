<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\OwnershipTransfer;
use App\Models\Project;
use App\Policies\OwnershipTransferPolicy;
use App\Services\ClientNotificationService;
use App\Services\ProjectTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class OwnershipTransferController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $dateFormat = (string) config('app.date_format', 'd-m-Y');

        $transfers = OwnershipTransfer::query()
            ->with(['project:id,name', 'fromCustomer:id,name', 'toCustomer:id,name'])
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Admin/Transfers/Index', [
            'pageTitle' => 'Ownership Transfers',
            'routes' => [
                'index' => route('admin.projects.transfers.index'),
            ],
            'transfers' => collect($transfers->items())->map(function (OwnershipTransfer $transfer) use ($dateFormat) {
                return [
                    'id' => $transfer->id,
                    'project_name' => (string) ($transfer->project?->name ?? '--'),
                    'from_customer_name' => (string) ($transfer->fromCustomer?->name ?? '--'),
                    'to_customer_name' => (string) ($transfer->toCustomer?->name ?? '--'),
                    'status' => (string) $transfer->status,
                    'status_label' => ucfirst((string) $transfer->status),
                    'scheduled_for' => $transfer->scheduled_for?->format($dateFormat.' H:i') ?? '--',
                    'created_at' => $transfer->created_at?->format($dateFormat.' H:i') ?? '--',
                    'can_cancel' => in_array($transfer->status, ['pending', 'accepted'], true),
                    'routes' => [
                        'cancel' => route('admin.projects.transfers.cancel', $transfer),
                    ],
                ];
            })->values()->all(),
            'pagination' => [
                'has_pages' => $transfers->hasPages(),
                'previous_url' => $transfers->previousPageUrl(),
                'next_url' => $transfers->nextPageUrl(),
            ],
        ]);
    }

    public function store(
        Request $request,
        Project $project,
        ProjectTransferService $service,
        ClientNotificationService $clientNotifications
    ): RedirectResponse {
        abort_unless(app(OwnershipTransferPolicy::class)->initiate($request->user(), $project), 403);

        $data = $request->validate([
            'to_customer_id' => [
                'required',
                'exists:customers,id',
                Rule::notIn([$project->customer_id]),
            ],
            'reason' => ['nullable', 'string', 'max:1000'],
            'scheduled_for' => ['nullable', 'date', 'after:now'],
        ]);

        $eligibilityError = $service->eligibilityError($project);
        if ($eligibilityError) {
            return redirect()->route('admin.projects.show', $project)->with('error', $eligibilityError);
        }

        $toCustomer = Customer::findOrFail($data['to_customer_id']);
        $scheduledFor = ! empty($data['scheduled_for']) ? \Carbon\Carbon::parse($data['scheduled_for']) : null;

        $transfer = $service->initiate(
            $project,
            $toCustomer,
            $request->user(),
            $data['reason'] ?? null,
            $scheduledFor,
            (string) $request->ip()
        );

        $clientNotifications->sendTransferInvite($transfer, $transfer->plainToken);

        return redirect()->route('admin.projects.show', $project)
            ->with('status', 'Transfer invite sent to '.$toCustomer->name.'.');
    }

    public function cancel(Request $request, OwnershipTransfer $transfer, ProjectTransferService $service): RedirectResponse
    {
        $this->authorize('cancel', $transfer);

        if (! in_array($transfer->status, ['pending', 'accepted'], true)) {
            return redirect()->route('admin.projects.transfers.index')
                ->with('error', 'Only pending or accepted transfers can be cancelled.');
        }

        $service->cancel($transfer, $request->user(), (string) $request->ip());

        return redirect()->route('admin.projects.transfers.index')
            ->with('status', 'Transfer cancelled.');
    }
}
