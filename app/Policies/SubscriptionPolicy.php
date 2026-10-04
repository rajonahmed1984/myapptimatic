<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

/**
 * Subscriptions drive billing and licensing, so only admins manage them, the
 * same rule LicensePolicy applies to licenses. The admin panel can be opened
 * to other roles through ADMIN_PANEL_ROLES; this keeps those roles out.
 */
class SubscriptionPolicy
{
    public function viewAny($actor): bool
    {
        return $this->isAdmin($actor);
    }

    public function view($actor, Subscription $subscription): bool
    {
        return $this->isAdmin($actor);
    }

    public function create($actor): bool
    {
        return $this->isAdmin($actor);
    }

    public function update($actor, Subscription $subscription): bool
    {
        return $this->isAdmin($actor);
    }

    public function delete($actor, Subscription $subscription): bool
    {
        return $this->isAdmin($actor);
    }

    private function isAdmin($actor): bool
    {
        return $actor instanceof User && $actor->isAdmin();
    }
}
