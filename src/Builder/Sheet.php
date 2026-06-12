<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Feature\Chart\ChartType;
use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Feature\Validation\ColorScaleNode;
use PHPdot\Sheets\Engine\Feature\Validation\DataBarNode;
use PHPdot\Sheets\Engine\Feature\Validation\DuplicateValuesNode;
use PHPdot\Sheets\Engine\Feature\Validation\ExpressionFormatNode;
use PHPdot\Sheets\Engine\Feature\Validation\IconSet;
use PHPdot\Sheets\Engine\Feature\Validation\IconSetNode;
use PHPdot\Sheets\Engine\Feature\Validation\ValidationType;
use PHPdot\Sheets\Engine\Model\Color;
use PHPdot\Sheets\Engine\Model\Orientation;
use PHPdot\Sheets\Engine\Model\PageSetup;
use PHPdot\Sheets\Engine\Model\SheetOptions;
use PHPdot\Sheets\Engine\Model\Style;
use PHPdot\Sheets\Engine\Support\ColumnRef;
use PHPdot\Sheets\Engine\Support\InvalidArgumentException;
use PHPdot\Sheets\Engine\Support\RuntimeException;
use PHPdot\Sheets\Engine\Xlsx\Writer;

/**
 * A worksheet being written. Returned by {@see Workbook::addSheet()}.
 *
 * Layout (`widths`, `freezeRows`, `tabColor`, …) must precede the first row,
 * because the engine writes the worksheet's column and view markup before the
 * row data. So the sheet stays "buffering" — collecting layout — and only
 * actually starts (writing that markup) on the first row or at finalize; a layout
 * call after the first row throws.
 *
 * Rows stream straight to disk (O(1) memory); `addRow()` hands back the "current"
 * row, held buffered until the next row flushes it (forward-only).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class Sheet
{
    private bool $started = false;
    private bool $finalized = false;
    private ?Row $pending = null;
    private int $rowCursor = 0;

    /** @var list<FeatureBuilder> Pending feature builders, committed at finalize. */
    private array $features = [];

    /** @var list<string> Merged ranges (trailers; applied at finalize). */
    private array $merges = [];

    /** @var list<array{cell: string, url: string, tooltip: string|null}> */
    private array $links = [];

    /** @var list<array{cell: string, text: string, author: string|null}> */
    private array $comments = [];

    private ?string $autoFilter = null;

    /** @var array<int, float> 0-based column index => width. */
    private array $columnWidths = [];
    private bool $autoSize = false;
    private int $frozenRows = 0;
    private int $frozenColumns = 0;
    private bool $showGridLines = true;
    private ?Color $tabColor = null;
    private bool $sheetHidden = false;

    /** @var list<int> 0-based hidden column indices. */
    private array $hiddenColumns = [];
    private bool $protect = false;
    private ?string $password = null;

    private bool $hasPageSetup = false;
    private Orientation $orientation = Orientation::Portrait;
    private ?int $fitToWidth = null;
    private ?int $fitToHeight = null;
    private ?string $printArea = null;
    private ?int $repeatRows = null;
    private ?string $header = null;
    private ?string $footer = null;

    public function __construct(
        private readonly Writer $writer,
        private readonly string $name,
    ) {}

    // -------------------------------------------------------------- layout --

    /**
     * Set explicit column widths by letter, e.g. `widths(['A' => 30, 'C' => 12.5])`.
     *
     * @param array<string, int|float> $widths
     */
    public function widths(array $widths): self
    {
        $this->assertBuffering();
        foreach ($widths as $column => $width) {
            $this->columnWidths[ColumnRef::number(strtoupper($column)) - 1] = (float) $width;
        }

        return $this;
    }

    /**
     * Size each column to its longest value (a display-width estimate — a
     * streaming writer has no font metrics). Manual `widths()` override it.
     */
    public function autoSize(): self
    {
        $this->assertBuffering();
        $this->autoSize = true;

        return $this;
    }

    public function freezeRows(int $rows): self
    {
        $this->assertBuffering();
        $this->frozenRows = $rows;

        return $this;
    }

    public function freezeColumns(int $columns): self
    {
        $this->assertBuffering();
        $this->frozenColumns = $columns;

        return $this;
    }

    public function withoutGridlines(): self
    {
        $this->assertBuffering();
        $this->showGridLines = false;

        return $this;
    }

    /**
     * Sheet tab color as a {@see Color} or a hex string ("FF5500", "#FF5500").
     */
    public function tabColor(Color|string $color): self
    {
        $this->assertBuffering();
        $this->tabColor = $color instanceof Color ? $color : Color::hex($color);

        return $this;
    }

    public function hide(): self
    {
        $this->assertBuffering();
        $this->sheetHidden = true;

        return $this;
    }

    public function hideColumns(string ...$columns): self
    {
        $this->assertBuffering();
        foreach ($columns as $column) {
            $this->hiddenColumns[] = ColumnRef::number(strtoupper($column)) - 1;
        }

        return $this;
    }

    /**
     * Lock the sheet (a deterrent, not encryption — the legacy 16-bit hash).
     */
    public function protect(?string $password = null): self
    {
        $this->assertBuffering();
        $this->protect = true;
        $this->password = $password;

        return $this;
    }

    public function landscape(): self
    {
        $this->assertBuffering();
        $this->orientation = Orientation::Landscape;
        $this->hasPageSetup = true;

        return $this;
    }

    public function portrait(): self
    {
        $this->assertBuffering();
        $this->orientation = Orientation::Portrait;
        $this->hasPageSetup = true;

        return $this;
    }

    public function fitToWidth(int $pages = 1): self
    {
        $this->assertBuffering();
        $this->fitToWidth = $pages;
        $this->hasPageSetup = true;

        return $this;
    }

    public function fitToHeight(int $pages = 1): self
    {
        $this->assertBuffering();
        $this->fitToHeight = $pages;
        $this->hasPageSetup = true;

        return $this;
    }

    public function printArea(string $range): self
    {
        $this->assertBuffering();
        $this->printArea = $range;
        $this->hasPageSetup = true;

        return $this;
    }

    public function repeatRows(int $rows): self
    {
        $this->assertBuffering();
        $this->repeatRows = $rows;
        $this->hasPageSetup = true;

        return $this;
    }

    public function pageHeader(string $text): self
    {
        $this->assertBuffering();
        $this->header = $text;
        $this->hasPageSetup = true;

        return $this;
    }

    public function pageFooter(string $text): self
    {
        $this->assertBuffering();
        $this->footer = $text;
        $this->hasPageSetup = true;

        return $this;
    }

    // ---------------------------------------------------------------- rows --

    /**
     * Append a row of scalar values and return it for optional decoration.
     * The first row locks the layout.
     *
     * @param list<int|float|string|bool|null> $values
     */
    public function addRow(array $values = []): Row
    {
        if ($this->finalized) {
            throw new RuntimeException('This sheet is finalized; a later sheet was started after it.');
        }

        $this->start();
        $this->flush();
        $this->rowCursor++;

        return $this->pending = new Row($values);
    }

    /**
     * The current (most recently added) row number, 1-based; 0 before any row.
     * Pairs with the `{row}` token in formulas.
     */
    public function currentRow(): int
    {
        return $this->rowCursor;
    }

    /**
     * Write a header row and remember its labels (for column-aware helpers).
     *
     * @param list<int|float|string|bool|null> $labels
     */
    public function header(array $labels, Style|int|null $style = null): Row
    {
        $row = $this->addRow($labels);
        if ($style !== null) {
            $row->style($style);
        }

        return $row;
    }

    /**
     * A sheet-qualified absolute cell reference for charts / named ranges:
     * `cellRef('D1')` → `Sales!$D$1`.
     */
    public function cellRef(string $cell): string
    {
        return $this->qualify($this->toAbsolute($cell));
    }

    /**
     * A sheet-qualified absolute column range: `colRef('D', 2, 6)` → `Sales!$D$2:$D$6`.
     */
    public function colRef(string $column, int $from, int $to): string
    {
        $letter = strtoupper($column);

        return $this->qualify('$' . $letter . '$' . $from . ':$' . $letter . '$' . $to);
    }

    // ------------------------------------------------------------ features --

    /**
     * Embed an image: a file path, or raw bytes with an explicit `$format`.
     * Position and size it with `->at($cell, [width, height])`.
     */
    public function addImage(string $source, ?string $format = null): Image
    {
        $image = new Image($source, $format);
        $this->addFeature($image);

        return $image;
    }

    /**
     * Add a chart anchored to this sheet, e.g. `addChart('bar')`. Configure it
     * fluently (series, title, legend, `->at()`); committed when the sheet flushes.
     */
    public function addChart(ChartType|string $type): Chart
    {
        $chart = new Chart($type);
        $this->addFeature($chart);

        return $chart;
    }

    /**
     * Highlight cells meeting a comparison:
     * `highlight('D2:D6')->greaterThan(1000)->fill($style)`.
     */
    public function highlight(string $range): Condition
    {
        $condition = new Condition($range);
        $this->addFeature($condition);

        return $condition;
    }

    /**
     * Highlight cells where a formula (relative to the top-left, e.g. `$C2>100`)
     * is true: `expression('A2:F100', '$C2>100')->fill($style)`.
     */
    public function expression(string $range, string $formula): FillRule
    {
        $rule = new FillRule(static fn(Style $style): FeatureNode => new ExpressionFormatNode($range, $formula, $style));
        $this->addFeature($rule);

        return $rule;
    }

    /**
     * Highlight duplicate values: `duplicates('A2:A100')->fill($style)`.
     */
    public function duplicates(string $range): FillRule
    {
        $rule = new FillRule(static fn(Style $style): FeatureNode => new DuplicateValuesNode($range, $style, false));
        $this->addFeature($rule);

        return $rule;
    }

    /**
     * Highlight unique values: `uniques('A2:A100')->fill($style)`.
     */
    public function uniques(string $range): FillRule
    {
        $rule = new FillRule(static fn(Style $style): FeatureNode => new DuplicateValuesNode($range, $style, true));
        $this->addFeature($rule);

        return $rule;
    }

    /**
     * Data bars across a range: `dataBars('D2:D6', '638EC6')`.
     */
    public function dataBars(string $range, Color|string $color): self
    {
        $this->addFeature(new PreparedFeature(new DataBarNode($range, $this->toColor($color))));

        return $this;
    }

    /**
     * A 2- or 3-color scale:
     * `colorScale('D2:D6', from: 'FFFFFF', to: '00B050', mid: 'FFEB84')`.
     */
    public function colorScale(string $range, Color|string $from, Color|string $to, Color|string|null $mid = null): self
    {
        $this->addFeature(new PreparedFeature(new ColorScaleNode(
            $range,
            $this->toColor($from),
            $this->toColor($to),
            $mid === null ? null : $this->toColor($mid),
        )));

        return $this;
    }

    /**
     * An icon set: `iconSet('D2:D6', '3arrows')` (or an {@see IconSet}).
     */
    public function iconSet(string $range, IconSet|string $set): self
    {
        $this->addFeature(new PreparedFeature(new IconSetNode($range, $this->toIconSet($set))));

        return $this;
    }

    /**
     * Add a data-validation rule, e.g.
     * `validate('B2:B100')->wholeNumber()->between(1, 100)`.
     */
    public function validate(string $range): Rule
    {
        $rule = new Rule($range);
        $this->addFeature($rule);

        return $rule;
    }

    /**
     * A dropdown of inline values: `dropdown('E2:E100', ['Yes', 'No'])`.
     *
     * @param list<string> $values
     */
    public function dropdown(string $range, array $values): Rule
    {
        $rule = new Rule($range, ValidationType::List, $values);
        $this->addFeature($rule);

        return $rule;
    }

    /**
     * A dropdown sourced from a range: `dropdownFrom('E2:E100', 'Lists!$A$1:$A$5')`.
     */
    public function dropdownFrom(string $range, string $source): Rule
    {
        $rule = new Rule($range, ValidationType::List, listRange: $source);
        $this->addFeature($rule);

        return $rule;
    }

    // -------------------------------------------------------- cell actions --

    /**
     * Merge a rectangular range, e.g. `merge('A1:D1')`. A trailer — callable any
     * time before the sheet closes (does not lock layout).
     */
    public function merge(string $range): self
    {
        $this->assertNotFinalized();
        $this->merges[] = $range;

        return $this;
    }

    /**
     * Link a cell to an external URL: `link('A1', 'https://…', tooltip: '…')`.
     */
    public function link(string $cell, string $url, ?string $tooltip = null): self
    {
        $this->assertNotFinalized();
        $this->links[] = ['cell' => $cell, 'url' => $url, 'tooltip' => $tooltip];

        return $this;
    }

    /**
     * Attach a comment to a cell: `comment('B2', 'Check this', author: 'Omar')`.
     */
    public function comment(string $cell, string $text, ?string $author = null): self
    {
        $this->assertNotFinalized();
        $this->comments[] = ['cell' => $cell, 'text' => $text, 'author' => $author];

        return $this;
    }

    /**
     * Enable filter dropdowns over a header range: `filter('A1:E1')`.
     */
    public function filter(string $range): self
    {
        $this->assertNotFinalized();
        $this->autoFilter = $range;

        return $this;
    }

    // ----------------------------------------------------------- datasets --

    /**
     * Write an iterable of associative rows with auto-derived headers (the keys
     * of the first row). The zero-config form of {@see self::iterate()}.
     *
     * @param iterable<array<int|string, mixed>> $rows
     */
    public function fill(iterable $rows): void
    {
        $this->iterate($rows)->write();
    }

    /**
     * Start a configurable dataset write (columns/casts/map/header style),
     * streaming the source row by row at O(1) memory.
     *
     * @param iterable<array<int|string, mixed>> $rows
     */
    public function iterate(iterable $rows): Dataset
    {
        return new Dataset($this, $rows);
    }

    /**
     * Start the sheet (write its layout markup) if it has not started, flush the
     * buffered row, and seal it against further writes.
     *
     * @internal Called by {@see Workbook} when the next sheet starts or on save.
     */
    public function finalize(): void
    {
        $this->start();
        $this->flush();
        $this->flushActions();
        $this->flushFeatures();
        $this->finalized = true;
    }

    private function start(): void
    {
        if ($this->started) {
            return;
        }

        $this->writer->startSheet($this->name, $this->buildOptions());
        $this->started = true;
    }

    private function flush(): void
    {
        $this->pending?->writeTo($this->writer, $this->rowCursor);
        $this->pending = null;
    }

    private function flushFeatures(): void
    {
        foreach ($this->features as $feature) {
            $this->writer->add($feature->toFeatureNode());
        }

        $this->features = [];
    }

    private function addFeature(FeatureBuilder $feature): void
    {
        $this->assertNotFinalized();
        $this->features[] = $feature;
    }

    private function flushActions(): void
    {
        foreach ($this->merges as $range) {
            $this->writer->mergeCells($range);
        }
        foreach ($this->links as $link) {
            $this->writer->hyperlink($link['cell'], $link['url'], $link['tooltip']);
        }
        foreach ($this->comments as $comment) {
            $this->writer->comment($comment['cell'], $comment['text'], $comment['author']);
        }
        if ($this->autoFilter !== null) {
            $this->writer->autoFilter($this->autoFilter);
        }
    }

    private function assertNotFinalized(): void
    {
        if ($this->finalized) {
            throw new RuntimeException('This sheet is finalized; a later sheet was started after it.');
        }
    }

    private function toAbsolute(string $ref): string
    {
        return preg_replace('/([A-Za-z]+)(\d+)/', '\$$1\$$2', $ref) ?? $ref;
    }

    private function qualify(string $ref): string
    {
        $name = preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $this->name) === 1
            ? $this->name
            : "'" . str_replace("'", "''", $this->name) . "'";

        return $name . '!' . $ref;
    }

    private function toColor(Color|string $color): Color
    {
        return $color instanceof Color ? $color : Color::hex($color);
    }

    private function toIconSet(IconSet|string $set): IconSet
    {
        if ($set instanceof IconSet) {
            return $set;
        }

        $normalized = strtolower($set);
        foreach (IconSet::cases() as $case) {
            if (strtolower($case->value) === $normalized) {
                return $case;
            }
        }

        throw new InvalidArgumentException(sprintf(
            'Unknown icon set "%s". Examples: 3arrows, 3trafficlights1, 4rating, 5quarters.',
            $set,
        ));
    }

    private function buildOptions(): SheetOptions
    {
        return new SheetOptions(
            showGridLines: $this->showGridLines,
            frozenRows: $this->frozenRows,
            frozenColumns: $this->frozenColumns,
            columnWidths: $this->columnWidths,
            autoSizeColumns: $this->autoSize,
            tabColor: $this->tabColor,
            hidden: $this->sheetHidden,
            hiddenColumns: $this->hiddenColumns,
            protectSheet: $this->protect,
            password: $this->password,
            pageSetup: $this->hasPageSetup ? $this->buildPageSetup() : null,
        );
    }

    private function buildPageSetup(): PageSetup
    {
        return new PageSetup(
            orientation: $this->orientation,
            fitToWidth: $this->fitToWidth,
            fitToHeight: $this->fitToHeight,
            header: $this->header,
            footer: $this->footer,
            printArea: $this->printArea,
            repeatRows: $this->repeatRows,
        );
    }

    private function assertBuffering(): void
    {
        if ($this->finalized) {
            throw new RuntimeException('This sheet is finalized.');
        }
        if ($this->started) {
            throw new RuntimeException('Sheet layout must be set before the first row.');
        }
    }
}
