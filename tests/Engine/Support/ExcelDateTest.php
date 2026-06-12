<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Tests\Engine\Support;

use PHPdot\Sheets\Engine\Support\ExcelDate;
use PHPUnit\Framework\TestCase;

final class ExcelDateTest extends TestCase
{
    public function testKnownSerials(): void
    {
        self::assertSame(1.0, ExcelDate::toSerial(self::utc('1900-01-01 00:00:00')));
        self::assertSame(61.0, ExcelDate::toSerial(self::utc('1900-03-01 00:00:00')));
        self::assertSame(25569.0, ExcelDate::toSerial(self::utc('1970-01-01 00:00:00')));
        self::assertSame(44927.0, ExcelDate::toSerial(self::utc('2023-01-01 00:00:00')));
    }

    public function testFractionalDay(): void
    {
        self::assertSame(44927.5, ExcelDate::toSerial(self::utc('2023-01-01 12:00:00')));
    }

    public function testToDateTime(): void
    {
        self::assertSame('1900-01-01', ExcelDate::toDateTime(1.0)->format('Y-m-d'));
        self::assertSame('1900-03-01', ExcelDate::toDateTime(61.0)->format('Y-m-d'));
        self::assertSame('2023-01-01', ExcelDate::toDateTime(44927.0)->format('Y-m-d'));
    }

    public function testRoundTrip(): void
    {
        $serial = ExcelDate::toSerial(self::utc('2024-06-08 13:30:00'));

        self::assertSame('2024-06-08 13:30:00', ExcelDate::toDateTime($serial)->format('Y-m-d H:i:s'));
    }

    private static function utc(string $datetime): \DateTimeImmutable
    {
        return new \DateTimeImmutable($datetime, new \DateTimeZone('UTC'));
    }
}
