<?php

namespace App\Enums;

class Role
{
    public const CLIENT = 'client';
    public const EMPLOYEE = 'employee';
    public const SALES = 'sales';
    public const SUPPORT = 'support';
    public const ADMIN = 'admin';
    public const MASTER_ADMIN = 'master_admin';
    public const SUB_ADMIN = 'sub_admin';

    public static function allowed(): array
    {
        return [
            self::CLIENT,
            self::EMPLOYEE,
            self::SALES,
            self::SUPPORT,
            self::ADMIN,
            self::MASTER_ADMIN,
            self::SUB_ADMIN,
        ];
    }

    public static function isValid(string $role): bool
    {
        return in_array($role, self::allowed(), true);
    }

    public static function adminRoles(): array
    {
        return [
            self::ADMIN,
            self::MASTER_ADMIN,
            self::SUB_ADMIN,
        ];
    }

    public static function adminPanelRoles(): array
    {
        return [
            self::MASTER_ADMIN,
            self::SUB_ADMIN,
            self::SALES,
            self::SUPPORT,
        ];
    }

    public static function labelMap(): array
    {
        return [
            self::MASTER_ADMIN => 'Master Admin',
            self::SUB_ADMIN => 'Sub Admin',
            self::ADMIN => 'Admin',
            self::SALES => 'Sales',
            self::SUPPORT => 'Support',
            self::EMPLOYEE => 'Employee',
            self::CLIENT => 'Client',
        ];
    }
}
