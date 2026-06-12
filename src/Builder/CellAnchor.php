<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Support\ColumnRef;
use PHPdot\Sheets\Engine\Support\InvalidArgumentException;

/**
 * Parses an A1 cell reference ("H17") to the engine's 0-based column/row.
 * Shared by the drawing builders ({@see Image}, {@see Chart}).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
trait CellAnchor
{
    /**
     * @return array{0: int, 1: int} [0-based column, 0-based row]
     */
    private function parseCellRef(string $cell): array
    {
        if (preg_match('/^([A-Za-z]+)([1-9]\d*)$/', $cell, $m) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid cell reference "%s" (expected e.g. "H17").', $cell));
        }

        return [ColumnRef::number(strtoupper($m[1])) - 1, (int) $m[2] - 1];
    }
}
