<?php

namespace App\Support\AuthFresh;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

class AdminAccess
{
    public static function canAccess(Authenticatable|null $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return in_array($user->role, self::allowedRoles(), true);
    }

    /**
     * @return array<int, string>
     */
    public static function allowedRoles(): array
    {
        $roles = config('admin.panel_roles', Role::adminPanelRoles());
        if (! is_array($roles) || $roles === []) {
            return Role::adminPanelRoles();
        }

        return array_values(array_unique(array_map('strval', $roles)));
    }
}
