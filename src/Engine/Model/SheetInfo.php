<?php

declare(strict_types=1);

/**
 * Immutable metadata describing a sheet discovered by a reader.
 *
 * `dimension` is the file's declared `<dimension>` used-range hint (e.g.
 * "A1:D100"); it may be absent or stale — never an authoritative row/column
 * count. Iterate the rows for the truth.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Model;

final class SheetInfo
{
    /**
     * Holds a reader-discovered sheet's index, name, and declared dimension hint.
     *
     * @param int $index
     * @param string $name
     * @param ?string $dimension
     */
    public function __construct(
        public readonly int $index,
        public readonly string $name,
        public readonly ?string $dimension = null,
    ) {}
}
