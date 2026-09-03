<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\License;
use App\Models\Order;
use App\Models\Project;
use App\Models\ProjectMaintenance;
use App\Models\StatusAuditLog;
use App\Models\Subscription;
use App\Support\SystemLogger;
use Illuminate\Support\Facades\DB;

/**
 * WHMCS-style "move to another client" for the three things that carry an
 * owner: subscriptions (with their licenses), licenses on their own, and
 * projects.
 *
 * Every move is one transaction and leaves an audit trail, because these
 * rewrite who is billed for what and there is no undo button.
 */
class OwnershipMoveService
{
    /**
     * Move a subscription — and, optionally, the projects, orders and invoices
     * hanging off it — to another customer. Licenses always travel with the
     * subscription: they belong to it, not to the customer.
     *
     * @param  array{projects?: bool, orders?: bool, invoices?: bool}  $include
     * @return array<string, int>
     */
    public function moveSubscription(
        Subscription $subscription,
        Customer $target,
        array $include = [],
        ?int $actorId = null
    ): array {
        $previousCustomerId = (int) $subscription->customer_id;

        if ($previousCustomerId === $target->id) {
            return ['moved' => 0];
        }

        $moved = DB::transaction(function () use ($subscription, $target, $include, $previousCustomerId) {
            $counts = [
                'licenses' => $subscription->licenses()->count(),
                'projects' => 0,
                'orders' => 0,
                'invoices' => 0,
                'maintenances' => 0,
            ];

            $subscription->update(['customer_id' => $target->id]);

            if (! empty($include['projects'])) {
                $projectIds = Project::where('subscription_id', $subscription->id)->pluck('id')->all();

                if (! empty($projectIds)) {
                    $counts['projects'] = Project::whereIn('id', $projectIds)
                        ->update(['customer_id' => $target->id]);
                    $counts['maintenances'] = ProjectMaintenance::whereIn('project_id', $projectIds)
                        ->update(['customer_id' => $target->id]);
                    $counts['invoices'] += Invoice::whereIn('project_id', $projectIds)
                        ->update(['customer_id' => $target->id]);
                }
            }

            if (! empty($include['orders'])) {
                $counts['orders'] = Order::where('subscription_id', $subscription->id)
                    ->update(['customer_id' => $target->id]);
            }

            if (! empty($include['invoices'])) {
                $counts['invoices'] += Invoice::where('subscription_id', $subscription->id)
                    ->update(['customer_id' => $target->id]);
            }

            return $counts;
        });

        $this->record(
            Subscription::class,
            $subscription->id,
            'Subscription moved to another client.',
            $previousCustomerId,
            $target->id,
            $moved,
            $actorId
        );

        return $moved;
    }

    /**
     * Move a single license to a different subscription. That subscription may
     * belong to a different customer, which is how a license changes hands
     * without dragging the whole subscription with it.
     *
     * @return array<string, mixed>
     */
    public function moveLicense(
        License $license,
        Subscription $targetSubscription,
        bool $moveDomains = true,
        ?int $actorId = null
    ): array {
        $previousSubscriptionId = (int) $license->subscription_id;

        if ($previousSubscriptionId === $targetSubscription->id) {
            return ['moved' => false];
        }

        $license->loadMissing('subscription');
        $previousCustomerId = (int) ($license->subscription?->customer_id ?? 0);

        $result = DB::transaction(function () use ($license, $targetSubscription, $moveDomains) {
            $updates = ['subscription_id' => $targetSubscription->id];

            // Keep the license pointed at a product the target subscription
            // actually sells; otherwise verification reports a product the new
            // owner never bought.
            $targetProductId = $targetSubscription->plan?->product_id;
            if ($targetProductId && (int) $license->product_id !== (int) $targetProductId) {
                $updates['product_id'] = $targetProductId;
            }

            $license->update($updates);

            if (! $moveDomains) {
                // The new owner runs the software somewhere else, so the old
                // bindings must not keep authorising the previous install.
                $license->domains()
                    ->where('status', 'active')
                    ->update(['status' => 'revoked']);
            }

            return [
                'moved' => true,
                'product_changed' => array_key_exists('product_id', $updates),
                'domains_revoked' => ! $moveDomains,
            ];
        });

        $this->record(
            License::class,
            $license->id,
            'License moved to another subscription.',
            $previousCustomerId,
            (int) $targetSubscription->customer_id,
            array_merge($result, [
                'from_subscription_id' => $previousSubscriptionId,
                'to_subscription_id' => $targetSubscription->id,
            ]),
            $actorId
        );

        return $result;
    }

    /**
     * Move a project to another client, taking the records that only make sense
     * under the same owner with it.
     *
     * @param  array{invoices?: bool, maintenances?: bool, tasks?: bool}  $include
     * @return array<string, int>
     */
    public function moveProject(
        Project $project,
        Customer $target,
        array $include = [],
        ?int $actorId = null
    ): array {
        $previousCustomerId = (int) $project->customer_id;

        if ($previousCustomerId === $target->id) {
            return ['moved' => 0];
        }

        $moved = DB::transaction(function () use ($project, $target, $include) {
            $counts = ['invoices' => 0, 'maintenances' => 0, 'client_users_detached' => 0];

            $project->update(['customer_id' => $target->id]);

            if (! empty($include['maintenances'])) {
                $counts['maintenances'] = ProjectMaintenance::where('project_id', $project->id)
                    ->update(['customer_id' => $target->id]);
            }

            if (! empty($include['invoices'])) {
                $counts['invoices'] = Invoice::where('project_id', $project->id)
                    ->update(['customer_id' => $target->id]);
            }

            // Portal users belonging to the previous client must lose access —
            // leaving them attached would show one client another's project.
            $counts['client_users_detached'] = $this->detachForeignProjectUsers($project, $target->id);

            return $counts;
        });

        $this->record(
            Project::class,
            $project->id,
            'Project moved to another client.',
            $previousCustomerId,
            $target->id,
            $moved,
            $actorId
        );

        return $moved;
    }

    private function detachForeignProjectUsers(Project $project, int $targetCustomerId): int
    {
        $foreignIds = $project->projectClients()
            ->where(function ($query) use ($targetCustomerId) {
                $query->whereNull('users.customer_id')
                    ->orWhere('users.customer_id', '!=', $targetCustomerId);
            })
            ->pluck('users.id');

        if ($foreignIds->isEmpty()) {
            return 0;
        }

        $project->projectClients()->detach($foreignIds->all());

        return $foreignIds->count();
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function record(
        string $modelType,
        int $modelId,
        string $message,
        int $fromCustomerId,
        int $toCustomerId,
        array $details,
        ?int $actorId
    ): void {
        StatusAuditLog::logChange(
            $modelType,
            $modelId,
            'customer:'.$fromCustomerId,
            'customer:'.$toCustomerId,
            'ownership_move',
            $actorId,
            $details
        );

        SystemLogger::write('activity', $message, array_merge([
            'model' => $modelType,
            'model_id' => $modelId,
            'from_customer_id' => $fromCustomerId,
            'to_customer_id' => $toCustomerId,
        ], $details), $actorId);
    }
}
