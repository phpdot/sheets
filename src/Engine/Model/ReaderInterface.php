<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Model;

/**
 * A forward-only, streaming spreadsheet reader.
 *
 * Worksheets are resolved through the workbook relationships, never by positional
 * file name — so files whose sheets were reordered or deleted read correctly.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface ReaderInterface
{
    /**
     * Metadata for every sheet, in workbook order.
     *
     * @return list<SheetInfo>
     */
    public function sheets(): array;

    /**
     * Stream a sheet's rows as Cell objects (rich). Keys are 1-based row numbers.
     * Null selects the first sheet. Numeric cells styled with a date number
     * format come back as `CellType::Date` with the value as a **1900-system**
     * Excel serial (legacy `date1904` workbooks are normalized, ISO `t="d"`
     * cells converted — see ExcelDate); error cells come back as `CellType::Error`.
     *
     * @return iterable<int<1, max>, list<Cell>>
     */
    public function rows(?int $sheetIndex = null): iterable;

    /**
     * Stream a sheet's rows as raw scalar values (fast). Keys are 1-based row numbers.
     * Null selects the first sheet.
     *
     * @return iterable<int<1, max>, list<int|float|string|bool|null>>
     */
    public function values(?int $sheetIndex = null): iterable;

    /**
     * Resolve a cell's `styleId` (from `Cell::$styleId`) back to a {@see Style}.
     * Returns null for the default style. (Theme/indexed colors are not resolved.)
     */
    public function style(?int $styleId): ?Style;

    /**
     * The merged cell ranges on a sheet (e.g. ["A1:D1", "A2:A5"]). Null selects the first sheet.
     *
     * @return list<string>
     */
    public function mergedCells(?int $sheetIndex = null): array;

    /**
     * Explicit column widths on a sheet. Null selects the first sheet.
     *
     * @return array<int, float> 1-based column index => width
     */
    public function columnWidths(?int $sheetIndex = null): array;

    /**
     * External hyperlinks on a sheet. Null selects the first sheet.
     *
     * @return array<string, string> cell reference => URL
     */
    public function hyperlinks(?int $sheetIndex = null): array;

    /**
     * Cell comments on a sheet. Null selects the first sheet.
     *
     * @return array<string, string> cell reference => comment text
     */
    public function comments(?int $sheetIndex = null): array;

    /**
     * Formula expressions on a sheet (the `<f>` text; `values()` returns cached results).
     * Null selects the first sheet.
     *
     * @return array<string, string> cell reference => formula
     */
    public function formulas(?int $sheetIndex = null): array;

    /**
     * Release the underlying file handle and any temporary resources.
     */
    public function close(): void;
}
