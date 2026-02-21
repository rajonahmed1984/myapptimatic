<?php

namespace App\Enums;

final class MailCategory
{
    public const BILLING = 'billing';
    public const SUPPORT = 'support';
    public const SYSTEM = 'system';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::BILLING,
            self::SUPPORT,
            self::SYSTEM,
        ];
    }

    public static function normalize(?string $category): string
    {
        $value = strtolower(trim((string) $category));

        return in_array($value, self::all(), true)
            ? $value
            : self::SYSTEM;
    }
}

