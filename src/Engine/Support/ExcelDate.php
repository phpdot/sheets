<?php

declare(strict_types=1);

/**
 * Excel 1900-system serial-date conversion, correct across the 1900 leap-year quirk.
 *
 * Excel serial 1 is 1900-01-01 and Excel wrongly treats 1900 as a leap year
 * (the phantom serial 60 = "1900-02-29"). Real calendar dates on or after
 * 1900-03-01 therefore carry a +1 offset, which this converter accounts for, so
 * every real date round-trips. Serial 60 does not correspond to any real date;
 * {@see self::toDateTime()} maps it to 1900-03-01.
 *
 * All conversions are performed in UTC.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Support;

final class ExcelDate
{
    /**
     * Days between the two Excel date systems: 1904-system serial 0
     * (1904-01-01) equals 1900-system serial 1462. Add this to a serial from a
     * `date1904` workbook to express it in the 1900 system this class uses.
     */
    public const SERIAL_1904_OFFSET = 1462;

    private const SECONDS_PER_DAY = 86400;

    /**
     * Not instantiable — the class exposes only static serial-date conversions.
     */
    private function __construct() {}

    /**
     * Convert a date/time to its Excel serial number.
     *
     * @param \DateTimeInterface $date
     *
     * @return float
     */
    public static function toSerial(\DateTimeInterface $date): float
    {
        $instant = new \DateTimeImmutable('@' . $date->getTimestamp());
        $midnight = $instant->setTime(0, 0, 0);

        $days = intdiv(
            $midnight->getTimestamp() - self::epochFor($midnight->format('Y-m-d'))->getTimestamp(),
            self::SECONDS_PER_DAY,
        );

        $fraction = ($instant->getTimestamp() - $midnight->getTimestamp()) / self::SECONDS_PER_DAY;

        return (float) $days + $fraction;
    }

    /**
     * Convert an Excel serial number to a UTC DateTimeImmutable.
     *
     * @param float $serial
     *
     * @return \DateTimeImmutable
     */
    public static function toDateTime(float $serial): \DateTimeImmutable
    {
        $whole = (int) floor($serial);
        $fraction = $serial - (float) $whole;

        $epoch = self::epochFor($whole >= 61 ? '1900-03-01' : '1900-01-01');

        $seconds = $whole * self::SECONDS_PER_DAY + (int) round($fraction * self::SECONDS_PER_DAY);

        return new \DateTimeImmutable('@' . ($epoch->getTimestamp() + $seconds));
    }

    /**
     * The epoch a date is measured from: dates on/after 1900-03-01 carry the
     * phantom-leap-day offset (epoch 1899-12-30), earlier dates do not (1899-12-31).
     *
     * @param string $ymd
     *
     * @return \DateTimeImmutable
     */
    private static function epochFor(string $ymd): \DateTimeImmutable
    {
        $epoch = $ymd >= '1900-03-01' ? '1899-12-30' : '1899-12-31';

        return new \DateTimeImmutable($epoch . 'T00:00:00+00:00');
    }
}
