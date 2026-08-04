<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\OwnershipTransfer;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;

class OwnershipTransferPolicy
{
    public function initiate($actor, Project $project): bool
    {
        if (! $actor instanceof User) {
            return false;
        }

        if (in_array($actor->role, [Role::ADMIN, Role::MASTER_ADMIN, Role::SUB_ADMIN], true)) {
            return true;
        }

        return $actor->isClient() && $actor->customer_id === $project->customer_id;
    }

    public function initiateForSubscription($actor, Subscription $subscription): bool
    {
        if (! $actor instanceof User) {
            return false;
        }

        if (in_array($actor->role, [Role::ADMIN, Role::MASTER_ADMIN, Role::SUB_ADMIN], true)) {
            return true;
        }

        return $actor->isClient() && $actor->customer_id === $subscription->customer_id;
    }

    public function view($actor, OwnershipTransfer $transfer): bool
    {
        if (! $actor instanceof User) {
            return false;
        }

        if (in_array($actor->role, [Role::ADMIN, Role::MASTER_ADMIN, Role::SUB_ADMIN], true)) {
            return true;
        }

        if (! $actor->isClient()) {
            return false;
        }

        return $actor->customer_id === $transfer->from_customer_id
            || $actor->customer_id === $transfer->to_customer_id;
    }

    public function accept($actor, OwnershipTransfer $transfer): bool
    {
        // Server-side only: never trust a client-supplied customer id. The token itself
        // (verified separately, before this check runs) proves possession of the invite —
        // this additionally confirms the acting user actually belongs to the receiving
        // customer, so a stolen/guessed transfer id can't be accepted by an unrelated account.
        return $actor instanceof User
            && $actor->isClient()
            && $actor->customer_id === $transfer->to_customer_id;
    }

    public function reject($actor, OwnershipTransfer $transfer): bool
    {
        return $this->accept($actor, $transfer);
    }

    public function cancel($actor, OwnershipTransfer $transfer): bool
    {
        if (! $actor instanceof User) {
            return false;
        }

        if (in_array($actor->role, [Role::ADMIN, Role::MASTER_ADMIN, Role::SUB_ADMIN], true)) {
            return true;
        }

        return $actor->isClient() && $actor->customer_id === $transfer->from_customer_id;
    }
}
