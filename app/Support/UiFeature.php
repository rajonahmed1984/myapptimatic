<?php

namespace App\Support;

class UiFeature
{
    public const REACT_SANDBOX = 'react_sandbox';

    public const ADMIN_EXPENSES_RECURRING_INDEX = 'admin_expenses_recurring_index';

    public const ADMIN_EXPENSES_RECURRING_SHOW = 'admin_expenses_recurring_show';

    public static function enabled(string $feature): bool
    {
        return (bool) config("features.{$feature}", false);
    }

    /**
     * @return array<string, bool>
     */
    public static function all(): array
    {
        return [
            self::REACT_SANDBOX => self::enabled(self::REACT_SANDBOX),
            self::ADMIN_EXPENSES_RECURRING_INDEX => self::enabled(self::ADMIN_EXPENSES_RECURRING_INDEX),
            self::ADMIN_EXPENSES_RECURRING_SHOW => self::enabled(self::ADMIN_EXPENSES_RECURRING_SHOW),
        ];
    }
}
