<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\OwnershipTransfer;
use App\Models\Project;
use App\Models\StatusAuditLog;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectTransferService
{
    public function eligibilityError(Project $project): ?string
    {
        if (! $project->subscription_id) {
            return 'This project has no linked subscription and cannot be transferred.';
        }

        if (Project::where('subscription_id', $project->subscription_id)->count() > 1) {
            return 'The linked subscription is shared by multiple projects for this customer and cannot be transferred as a unit.';
        }

        return null;
    }

    /**
     * Eligibility for the subscription-anchored flow (product/subscription/license,
     * with no project required). If exactly one project happens to be linked to this
     * subscription it will move along automatically; more than one is ambiguous and blocked,
     * same reasoning as the project-anchored flow.
     */
    public function eligibilityErrorForSubscription(Subscription $subscription): ?string
    {
        if (Project::where('subscription_id', $subscription->id)->count() > 1) {
            return 'This subscription is linked to multiple projects and cannot be transferred as a unit.';
        }

        return null;
    }

    public function initiate(
        Project $project,
        Customer $toCustomer,
        User $initiator,
        ?string $reason,
        ?Carbon $scheduledFor,
        string $ip
    ): OwnershipTransfer {
        return $this->createTransfer([
            'project_id' => $project->id,
            'subscription_id' => $project->subscription_id,
            'from_customer_id' => $project->customer_id,
            'to_customer_id' => $toCustomer->id,
        ], $initiator, $reason, $scheduledFor, $ip);
    }

    /**
     * Subscription-anchored initiation — moves the subscription + its licenses (+ a
     * linked project, if exactly one exists) without requiring a project up front.
     */
    public function initiateForSubscription(
        Subscription $subscription,
        Customer $toCustomer,
        User $initiator,
        ?string $reason,
        ?Carbon $scheduledFor,
        string $ip
    ): OwnershipTransfer {
        $linkedProject = Project::where('subscription_id', $subscription->id)->first();

        return $this->createTransfer([
            'project_id' => $linkedProject?->id,
            'subscription_id' => $subscription->id,
            'from_customer_id' => $subscription->customer_id,
            'to_customer_id' => $toCustomer->id,
        ], $initiator, $reason, $scheduledFor, $ip);
    }

    private function createTransfer(array $attributes, User $initiator, ?string $reason, ?Carbon $scheduledFor, string $ip): OwnershipTransfer
    {
        $plainToken = Str::random(64);

        $transfer = DB::transaction(function () use ($attributes, $initiator, $reason, $scheduledFor, $ip, $plainToken) {
            $transfer = OwnershipTransfer::create(array_merge($attributes, [
                'initiated_by' => $initiator->id,
                'initiated_by_ip' => $ip,
                'status' => 'pending',
                'token_hash' => hash('sha256', $plainToken),
                'token_expires_at' => now()->addDays(7),
                'scheduled_for' => $scheduledFor,
                'reason' => $reason,
            ]));

            $transfer->logs()->create([
                'action' => 'created',
                'actor_user_id' => $initiator->id,
                'ip_address' => $ip,
            ]);

            return $transfer;
        });

        $transfer->plainToken = $plainToken;

        return $transfer;
    }

    public function accept(OwnershipTransfer $transfer, User $acceptor, string $ip): void
    {
        DB::transaction(function () use ($transfer, $acceptor, $ip) {
            $transfer->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'accepted_by_user_id' => $acceptor->id,
                'accepted_by_ip' => $ip,
            ]);

            $transfer->logs()->create([
                'action' => 'accepted',
                'actor_user_id' => $acceptor->id,
                'ip_address' => $ip,
            ]);

            if (! $transfer->scheduled_for || $transfer->scheduled_for->isPast()) {
                $this->execute($transfer);
            }
        });
    }

    public function reject(OwnershipTransfer $transfer, User $rejector, string $ip): void
    {
        DB::transaction(function () use ($transfer, $rejector, $ip) {
            $transfer->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejected_by_user_id' => $rejector->id,
                'rejected_by_ip' => $ip,
            ]);

            $transfer->logs()->create([
                'action' => 'rejected',
                'actor_user_id' => $rejector->id,
                'ip_address' => $ip,
            ]);
        });
    }

    public function cancel(OwnershipTransfer $transfer, User $canceller, string $ip): void
    {
        DB::transaction(function () use ($transfer, $canceller, $ip) {
            $transfer->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $canceller->id,
            ]);

            $transfer->logs()->create([
                'action' => 'cancelled',
                'actor_user_id' => $canceller->id,
                'ip_address' => $ip,
            ]);
        });
    }

    public function execute(OwnershipTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            $transfer->loadMissing(['project', 'subscription']);
            $project = $transfer->project;
            $subscription = $transfer->subscription;
            $oldCustomerId = $subscription->customer_id;

            // Licenses need no direct update — they only key off subscription_id, so
            // reassigning the subscription's owner "moves" every license under it too.
            // Mirrors the core reassignment SubscriptionController::moveOwner() already does.
            $subscription->update(['customer_id' => $transfer->to_customer_id]);
            // project_id is optional — the subscription-anchored flow (product/subscription/
            // license only) has no project at all.
            $project?->update(['customer_id' => $transfer->to_customer_id]);

            $transfer->update([
                'status' => 'executed',
                'executed_at' => now(),
            ]);

            $transfer->logs()->create([
                'action' => 'executed',
                'metadata' => [
                    'from_customer_id' => $oldCustomerId,
                    'to_customer_id' => $transfer->to_customer_id,
                ],
            ]);

            StatusAuditLog::logChange(
                $project ? Project::class : Subscription::class,
                $project?->id ?? $subscription->id,
                'pending_transfer',
                'transferred',
                'ownership_transfer',
                $transfer->accepted_by_user_id,
                [
                    'from_customer_id' => $oldCustomerId,
                    'to_customer_id' => $transfer->to_customer_id,
                    'ownership_transfer_id' => $transfer->id,
                ]
            );
        });
    }

    public function expireStale(): int
    {
        $expired = 0;

        OwnershipTransfer::query()
            ->where('status', 'pending')
            ->where('token_expires_at', '<', now())
            ->chunkById(100, function ($transfers) use (&$expired) {
                foreach ($transfers as $transfer) {
                    $transfer->update(['status' => 'expired']);
                    $transfer->logs()->create(['action' => 'expired']);
                    $expired++;
                }
            });

        return $expired;
    }

    public function executeDueScheduled(): int
    {
        $executed = 0;

        OwnershipTransfer::query()
            ->where('status', 'accepted')
            ->whereNotNull('scheduled_for')
            ->whereNull('executed_at')
            ->where('scheduled_for', '<=', now())
            ->chunkById(100, function ($transfers) use (&$executed) {
                foreach ($transfers as $transfer) {
                    $this->execute($transfer);
                    $executed++;
                }
            });

        return $executed;
    }
}
