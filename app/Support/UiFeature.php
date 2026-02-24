<?php

namespace App\Support;

class UiFeature
{
    public const REACT_SANDBOX = 'react_sandbox';

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
        ];
    }
}
