<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Xlsx;

/**
 * A monotonic counter used to vend unique package part numbers (drawingN.xml,
 * imageN.png, …) across all sheets, so parts never collide.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class PartCounter
{
    private int $value = 0;

    public function next(): int
    {
        return ++$this->value;
    }
}
