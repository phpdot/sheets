<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Model;

/**
 * An immutable row of cells — the rich-path value object.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class Row
{
    /**
     * @param list<Cell> $cells
     */
    public function __construct(
        public readonly array $cells,
        public readonly ?int $styleId = null,
        public readonly ?float $height = null,
        public readonly bool $hidden = false,
    ) {}

    /**
     * The number of cells in the row.
     */
    public function count(): int
    {
        return count($this->cells);
    }
}
