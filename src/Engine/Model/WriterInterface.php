<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Model;

use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Feature\FeaturePlugin;

/**
 * A forward-only, streaming spreadsheet writer bound to one output file.
 *
 * Stateful per operation — created per `createWriter()`, never shared as a singleton.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface WriterInterface
{
    /**
     * Enable feature plugins (registers their serializers) for this writer.
     */
    public function use(FeaturePlugin ...$plugins): static;

    /**
     * Register a style and return its integer id, referenced by `addRow()` and cells.
     * Identical styles return the same id (deduplicated).
     */
    public function registerStyle(Style $style): int;

    /**
     * Begin a new sheet. Flushes any open sheet first.
     */
    public function startSheet(string $name, ?SheetOptions $options = null): void;

    /**
     * Fast path: append a row of raw scalar values with no per-cell object allocation.
     *
     * @param list<int|float|string|bool|null> $values
     * @param float|null $height Optional row height in points.
     * @param bool $hidden Hide the row.
     */
    public function addRow(array $values, ?int $styleId = null, ?float $height = null, bool $hidden = false): void;

    /**
     * Rich path: append a row of Cell objects (per-cell types and styles).
     */
    public function addRowObject(Row $row): void;

    /**
     * Merge a rectangular cell range (e.g. "A1:D1") on the current sheet.
     */
    public function mergeCells(string $range): void;

    /**
     * Add a hyperlink to an external URL on a cell of the current sheet.
     */
    public function hyperlink(string $cell, string $url, ?string $tooltip = null): void;

    /**
     * Enable filter dropdowns over a header range (e.g. "A1:E1") on the current sheet.
     */
    public function autoFilter(string $range): void;

    /**
     * Define a workbook-level named range (e.g. name "Sales", formula "Sheet1!$A$1:$B$9").
     */
    public function defineName(string $name, string $formula): void;

    /**
     * Attach a comment (note) to a cell of the current sheet.
     */
    public function comment(string $cell, string $text, ?string $author = null): void;

    /**
     * Buffer a feature node (chart, image, rule); serialized at `close()`.
     */
    public function add(FeatureNode $node): void;

    /**
     * Finalize: serialize features, write remaining parts, and zip the output.
     */
    public function close(): void;
}
