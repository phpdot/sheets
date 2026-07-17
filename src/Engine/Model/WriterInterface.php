<?php

declare(strict_types=1);

/**
 * A forward-only, streaming spreadsheet writer bound to one output file.
 *
 * Stateful per operation — created per `createWriter()`, never shared as a singleton.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Model;

use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Feature\FeaturePlugin;

interface WriterInterface
{
    /**
     * Enable feature plugins (registers their serializers) for this writer.
     *
     * @param FeaturePlugin $plugins
     *
     * @return static
     */
    public function use(FeaturePlugin ...$plugins): static;

    /**
     * Register a style and return its integer id, referenced by `addRow()` and cells.
     * Identical styles return the same id (deduplicated).
     *
     * @param Style $style
     *
     * @return int
     */
    public function registerStyle(Style $style): int;

    /**
     * Begin a new sheet. Flushes any open sheet first.
     *
     * @param string $name
     * @param ?SheetOptions $options
     *
     * @return void
     */
    public function startSheet(string $name, ?SheetOptions $options = null): void;

    /**
     * Fast path: append a row of raw scalar values with no per-cell object allocation.
     *
     * @param list<int|float|string|bool|null> $values
     * @param float|null $height Optional row height in points.
     * @param bool $hidden Hide the row.
     * @param ?int $styleId
     *
     * @return void
     */
    public function addRow(array $values, ?int $styleId = null, ?float $height = null, bool $hidden = false): void;

    /**
     * Rich path: append a row of Cell objects (per-cell types and styles).
     *
     * @param Row $row
     *
     * @return void
     */
    public function addRowObject(Row $row): void;

    /**
     * Merge a rectangular cell range (e.g. "A1:D1") on the current sheet.
     *
     * @param string $range
     *
     * @return void
     */
    public function mergeCells(string $range): void;

    /**
     * Add a hyperlink to an external URL on a cell of the current sheet.
     *
     * @param string $cell
     * @param string $url
     * @param ?string $tooltip
     *
     * @return void
     */
    public function hyperlink(string $cell, string $url, ?string $tooltip = null): void;

    /**
     * Enable filter dropdowns over a header range (e.g. "A1:E1") on the current sheet.
     *
     * @param string $range
     *
     * @return void
     */
    public function autoFilter(string $range): void;

    /**
     * Define a workbook-level named range (e.g. name "Sales", formula "Sheet1!$A$1:$B$9").
     *
     * @param string $formula
     * @param string $name
     *
     * @return void
     */
    public function defineName(string $name, string $formula): void;

    /**
     * Attach a comment (note) to a cell of the current sheet.
     *
     * @param string $text
     * @param ?string $author
     * @param string $cell
     *
     * @return void
     */
    public function comment(string $cell, string $text, ?string $author = null): void;

    /**
     * Buffer a feature node (chart, image, rule); serialized at `close()`.
     *
     * @param FeatureNode $node
     *
     * @return void
     */
    public function add(FeatureNode $node): void;

    /**
     * Finalize: serialize features, write remaining parts, and zip the output.
     *
     * @return void
     */
    public function close(): void;
}
