<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;

class DateTimeFormat
{
    public const DATE_PATTERN = 'd-m-Y';
    public const TIME_PATTERN = 'h:i A';

    public static function datePattern(): string
    {
        return self::DATE_PATTERN;
    }

    public static function timePattern(): string
    {
        return self::TIME_PATTERN;
    }

    public static function dateTimePattern(): string
    {
        return self::datePattern().' '.self::timePattern();
    }

    public static function formatDate(mixed $value, string $fallback = '-'): string
    {
        $dateTime = self::normalize($value);

        if (! $dateTime) {
            return $fallback;
        }

        return $dateTime->format(self::datePattern());
    }

    public static function formatDateTime(mixed $value, string $fallback = '-'): string
    {
        $dateTime = self::normalize($value);

        if (! $dateTime) {
            return $fallback;
        }

        return $dateTime->format(self::dateTimePattern());
    }

    public static function formatTime(mixed $value, string $fallback = '-'): string
    {
        $dateTime = self::normalize($value);

        if (! $dateTime) {
            return $fallback;
        }

        return $dateTime->format(self::timePattern());
    }

    public static function parseDate(?string $value): ?CarbonImmutable
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $timezone = (string) config('app.timezone', 'UTC');
        $formats = [
            self::DATE_PATTERN,
            'Y-m-d',
            'd/m/Y',
        ];

        foreach ($formats as $format) {
            $parsed = CarbonImmutable::createFromFormat($format, $value, $timezone);
            if ($parsed && $parsed->format($format) === $value) {
                return $parsed->startOfDay();
            }
        }

        return null;
    }

    public static function parseDateTime(?string $value): ?CarbonImmutable
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $timezone = (string) config('app.timezone', 'UTC');
        $formats = [
            self::dateTimePattern(),
            'Y-m-d H:i',
            'Y-m-d H:i:s',
            'Y-m-d\TH:i',
            'Y-m-d\TH:i:s',
        ];

        foreach ($formats as $format) {
            $parsed = CarbonImmutable::createFromFormat($format, $value, $timezone);
            if ($parsed && $parsed->format($format) === $value) {
                return $parsed;
            }
        }

        try {
            return CarbonImmutable::parse($value, $timezone);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function parseTime(?string $value): ?CarbonImmutable
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $timezone = (string) config('app.timezone', 'UTC');
        $formats = [
            self::TIME_PATTERN,
            'H:i',
            'H:i:s',
        ];

        foreach ($formats as $format) {
            $parsed = CarbonImmutable::createFromFormat($format, $value, $timezone);
            if ($parsed && $parsed->format($format) === $value) {
                return $parsed;
            }
        }

        return null;
    }

    private static function normalize(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        try {
            return CarbonImmutable::parse((string) $value, (string) config('app.timezone', 'UTC'));
        } catch (\Throwable) {
            return null;
        }
    }
}
