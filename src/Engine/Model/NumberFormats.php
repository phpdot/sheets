<?php

declare(strict_types=1);

/**
 * Ready-made number-format codes for the common cases, to pass to
 * {@see Style::withNumberFormat()} instead of memorizing Excel format syntax.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Model;

final class NumberFormats
{
    public const INTEGER = '#,##0';
    public const DECIMAL = '#,##0.00';
    public const CURRENCY_USD = '"$"#,##0.00';
    public const CURRENCY_EUR = '"€"#,##0.00';
    public const ACCOUNTING_USD = '_("$"* #,##0.00_);_("$"* \(#,##0.00\);_("$"* "-"??_);_(@_)';
    public const PERCENT = '0%';
    public const PERCENT_2 = '0.00%';
    public const SCIENTIFIC = '0.00E+00';
    public const DATE = 'yyyy-mm-dd';
    public const DATE_US = 'mm/dd/yyyy';
    public const DATE_TIME = 'yyyy-mm-dd hh:mm:ss';
    public const TIME = 'hh:mm:ss';

    /**
     * Resolve a preset name ("currency", "percent", "date", …) to its format
     * code, or pass an explicit Excel format code through unchanged.
     *
     * @param string $format
     *
     * @return string
     */
    public static function resolve(string $format): string
    {
        return match (strtolower($format)) {
            'integer' => self::INTEGER,
            'decimal' => self::DECIMAL,
            'currency', 'currency_usd' => self::CURRENCY_USD,
            'currency_eur' => self::CURRENCY_EUR,
            'accounting' => self::ACCOUNTING_USD,
            'percent' => self::PERCENT,
            'percent_2' => self::PERCENT_2,
            'scientific' => self::SCIENTIFIC,
            'date' => self::DATE,
            'date_us' => self::DATE_US,
            'datetime', 'date_time' => self::DATE_TIME,
            'time' => self::TIME,
            default => $format,
        };
    }

    /**
     * Not instantiable — a static registry of ready-made number-format codes.
     */
    private function __construct() {}
}
