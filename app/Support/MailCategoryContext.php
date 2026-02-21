<?php

namespace App\Support;

use App\Enums\MailCategory;

final class MailCategoryContext
{
    /**
     * @var array<int, string>
     */
    private static array $stack = [];

    public static function current(): ?string
    {
        if (empty(self::$stack)) {
            return null;
        }

        return self::$stack[array_key_last(self::$stack)];
    }

    public static function set(?string $category): void
    {
        self::$stack[] = MailCategory::normalize($category);
    }

    public static function clear(): void
    {
        array_pop(self::$stack);
    }

    public static function run(?string $category, callable $callback): mixed
    {
        self::set($category);

        try {
            return $callback();
        } finally {
            self::clear();
        }
    }
}

