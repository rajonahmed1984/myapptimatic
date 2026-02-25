<?php

namespace Tests\Unit\Support;

use App\Support\DateTimeFormat;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DateTimeFormatTest extends TestCase
{
    #[Test]
    public function it_formats_date_time_and_time_using_the_project_standard(): void
    {
        $value = '2026-02-25 13:05:00';

        $this->assertSame('25-02-2026', DateTimeFormat::formatDate($value));
        $this->assertSame('01:05 PM', DateTimeFormat::formatTime($value));
        $this->assertSame('25-02-2026 01:05 PM', DateTimeFormat::formatDateTime($value));
    }

    #[Test]
    public function it_returns_fallback_for_empty_or_invalid_values(): void
    {
        $this->assertSame('-', DateTimeFormat::formatDate(null));
        $this->assertSame('-', DateTimeFormat::formatDateTime(''));
        $this->assertSame('-', DateTimeFormat::formatTime('not-a-date'));
    }

    #[Test]
    public function it_parses_dd_mm_yyyy_and_yyyy_mm_dd_date_inputs(): void
    {
        $fromDisplay = DateTimeFormat::parseDate('25-02-2026');
        $fromIso = DateTimeFormat::parseDate('2026-02-25');

        $this->assertNotNull($fromDisplay);
        $this->assertNotNull($fromIso);
        $this->assertSame('2026-02-25', $fromDisplay?->toDateString());
        $this->assertSame('2026-02-25', $fromIso?->toDateString());
    }

    #[Test]
    public function it_parses_project_standard_datetime_and_time_inputs(): void
    {
        $dateTime = DateTimeFormat::parseDateTime('25-02-2026 01:15 PM');
        $time = DateTimeFormat::parseTime('01:15 PM');

        $this->assertNotNull($dateTime);
        $this->assertNotNull($time);
        $this->assertSame('2026-02-25 13:15', $dateTime?->format('Y-m-d H:i'));
        $this->assertSame('13:15', $time?->format('H:i'));
    }
}

