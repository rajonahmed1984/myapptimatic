<?php

namespace App\Policies;

use App\Models\User;

class DocumentPolicy
{
    public function view(User $user): bool
    {
        return $user->isAdmin() || $user->isSupport();
    }
}
