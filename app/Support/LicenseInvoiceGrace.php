<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class LicenseInvoiceGrace
{
    public static function cutoff(?CarbonInterface $now = null): CarbonInterface
    {
        $timezone = (string) (Setting::getValue('time_zone', config('app.timezone', 'UTC')) ?: 'UTC');
        $localNow = $now ? $now->copy()->timezone($timezone) : Carbon::now($timezone);

        return $localNow->copy()->startOfMonth()->day(3)->setTime(23, 59);
    }

    public static function hasEnded(?CarbonInterface $now = null): bool
    {
        $timezone = (string) (Setting::getValue('time_zone', config('app.timezone', 'UTC')) ?: 'UTC');
        $localNow = $now ? $now->copy()->timezone($timezone) : Carbon::now($timezone);

        return $localNow->greaterThanOrEqualTo(self::cutoff($localNow));
    }
}
