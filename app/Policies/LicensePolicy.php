<?php

namespace App\Policies;

use App\Models\License;
use App\Models\User;

class LicensePolicy
{
    public function view($actor, License $license): bool
    {
        if ($actor instanceof User) {
            if ($actor->isAdmin()) {
                return true;
            }

            if ($actor->isClient()) {
                $customerId = $license->subscription?->customer_id;
                return $customerId && $actor->customer_id === $customerId;
            }
        }

        return false;
    }

    public function create($actor): bool
    {
        return $actor instanceof User && $actor->isAdmin();
    }

    public function update($actor, License $license): bool
    {
        return $actor instanceof User && $actor->isAdmin();
    }

    public function delete($actor, License $license): bool
    {
        return $actor instanceof User && $actor->isAdmin();
    }
}
