<?php

declare(strict_types=1);

/**
 * A monotonic counter used to vend unique package part numbers (drawingN.xml,
 * imageN.png, …) across all sheets, so parts never collide.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Xlsx;

final class PartCounter
{
    private int $value = 0;

    /**
     * Vends the next unique part number, incrementing the counter.
     *
     * @return int
     */
    public function next(): int
    {
        return ++$this->value;
    }
}
