<?php

namespace App\Support;

class UiFeature
{
    public const REACT_SANDBOX = 'react_sandbox';
    public const REACT_PUBLIC_PRODUCTS = 'react_public_products';

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
            self::REACT_PUBLIC_PRODUCTS => self::enabled(self::REACT_PUBLIC_PRODUCTS),
        ];
    }
}
