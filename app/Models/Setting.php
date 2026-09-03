<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Per-request cache of every setting row.
     *
     * A single billing run asks for dozens of settings inside per-invoice
     * loops, and each call used to be its own SELECT. The table is small, so
     * one read up front is cheaper than any of the alternatives.
     *
     * @var array<string, string>|null
     */
    private static ?array $cachedValues = null;

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $values = static::cachedValues();

        if (array_key_exists($key, $values)) {
            return $values[$key];
        }

        return $default ?? config("license.defaults.{$key}");
    }

    public static function setValue(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => (string) $value]);

        if (self::$cachedValues !== null) {
            self::$cachedValues[$key] = (string) $value;
        }
    }

    /**
     * Drop the per-request cache. Long-running processes (queue workers, the
     * billing cron) call this when they need to see settings changed elsewhere.
     */
    public static function flushCache(): void
    {
        self::$cachedValues = null;
    }

    /**
     * @return array<string, string>
     */
    private static function cachedValues(): array
    {
        if (self::$cachedValues === null) {
            try {
                self::$cachedValues = static::query()
                    ->pluck('value', 'key')
                    ->map(fn ($value) => (string) $value)
                    ->all();
            } catch (\Throwable) {
                // Never let a settings read break a request; fall through to
                // the config defaults instead.
                return [];
            }
        }

        return self::$cachedValues;
    }
}
