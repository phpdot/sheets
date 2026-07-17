<?php

declare(strict_types=1);

/**
 * An immutable row of cells — the rich-path value object.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Model;

final class Row
{
    /**
     * Wraps the row's cells with an optional style id, height, and hidden flag.
     *
     * @param list<Cell> $cells
     * @param ?int $styleId
     * @param ?float $height
     * @param bool $hidden
     */
    public function __construct(
        public readonly array $cells,
        public readonly ?int $styleId = null,
        public readonly ?float $height = null,
        public readonly bool $hidden = false,
    ) {}

    /**
     * The number of cells in the row.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->cells);
    }
}
