<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Model;

/**
 * Immutable per-sheet options applied when a sheet is started.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class SheetOptions
{
    /**
     * @param array<int, int|float> $columnWidths 0-based column index => width in characters.
     *                                            Overrides the auto-size estimate for that column.
     * @param bool $autoSizeColumns When true, each column is sized to its longest value (an estimate —
     *                              a streaming writer has no font metrics). Defers the sheet body.
     * @param list<int> $hiddenColumns 0-based column indices to hide.
     * @param bool $protectSheet Lock the sheet (cells are locked by default); optionally with $password.
     */
    public function __construct(
        public readonly bool $showGridLines = true,
        public readonly int $frozenRows = 0,
        public readonly int $frozenColumns = 0,
        public readonly array $columnWidths = [],
        public readonly bool $autoSizeColumns = false,
        public readonly ?Color $tabColor = null,
        public readonly bool $hidden = false,
        public readonly array $hiddenColumns = [],
        public readonly bool $protectSheet = false,
        public readonly ?string $password = null,
        public readonly ?PageSetup $pageSetup = null,
    ) {}
}
