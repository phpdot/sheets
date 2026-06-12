<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Model;

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
final class SheetInfo
{
    public function __construct(
        public readonly int $index,
        public readonly string $name,
        public readonly ?string $dimension = null,
    ) {}
}
