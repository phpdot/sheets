<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Support;

/**
 * Spreadsheet column-reference conversion (A=1 … XFD=16384).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ColumnRef
{
    /**
     * The maximum number of columns a worksheet may contain.
     */
    public const MAX = 16384;

    private function __construct() {}

    /**
     * Convert a 1-based column number to its letters (1 => "A", 27 => "AA", 16384 => "XFD").
     *
     * @throws InvalidArgumentException When the column is outside 1..MAX.
     */
    public static function letters(int $column): string
    {
        if ($column < 1 || $column > self::MAX) {
            throw new InvalidArgumentException(
                sprintf('Column %d is out of range (1..%d).', $column, self::MAX),
            );
        }

        $letters = '';

        while ($column > 0) {
            $column--;
            $letters = chr(65 + ($column % 26)) . $letters;
            $column = intdiv($column, 26);
        }

        return $letters;
    }

    /**
     * Convert column letters to a 1-based column number ("A" => 1, "AA" => 27).
     *
     * @throws InvalidArgumentException When empty, not A-Z, or out of range.
     */
    public static function number(string $letters): int
    {
        if (preg_match('/^[A-Z]+$/', $letters) !== 1) {
            throw new InvalidArgumentException(
                sprintf('Invalid column letters: "%s".', $letters),
            );
        }

        $number = 0;
        $length = strlen($letters);

        for ($i = 0; $i < $length; $i++) {
            $number = $number * 26 + (ord($letters[$i]) - 64);
        }

        if ($number > self::MAX) {
            throw new InvalidArgumentException(
                sprintf('Column "%s" exceeds the maximum (%d).', $letters, self::MAX),
            );
        }

        return $number;
    }
}
