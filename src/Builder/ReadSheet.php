<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Model\Cell;
use PHPdot\Sheets\Engine\Model\ReaderInterface;
use PHPdot\Sheets\Engine\Model\Style;
use PHPdot\Sheets\Engine\Support\ColumnRef;

/**
 * A sheet opened for reading — returned by {@see ReadWorkbook::sheet()}. Stream it
 * as raw `values()`, typed `rows()` (cells inspected via predicates), or assoc
 * `records()`; metadata (merged cells, widths, links, comments, formulas) mirrors
 * the engine reader but speaks A1/letters.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ReadSheet
{
    public function __construct(
        private readonly ReaderInterface $reader,
        private readonly int $index,
    ) {}

    /**
     * Raw scalar rows (fast). Keys are 1-based row numbers.
     *
     * @return iterable<int<1, max>, list<int|float|string|bool|null>>
     */
    public function values(): iterable
    {
        return $this->reader->values($this->index);
    }

    /**
     * Typed cells (rich). Keys are 1-based row numbers.
     *
     * @return iterable<int<1, max>, list<Cell>>
     */
    public function rows(): iterable
    {
        return $this->reader->rows($this->index);
    }

    /**
     * Rows as associative arrays keyed by the first (header) row.
     *
     * @return \Generator<int, array<string, int|float|string|bool|null>>
     */
    public function records(): \Generator
    {
        $header = null;
        foreach ($this->reader->values($this->index) as $cells) {
            if ($header === null) {
                $header = array_map(static fn($value): string => (string) $value, $cells);

                continue;
            }

            $record = [];
            foreach ($header as $i => $key) {
                $record[$key] = $cells[$i] ?? null;
            }
            yield $record;
        }
    }

    /**
     * Start a configurable read (rename/select columns, cast, map) — see {@see ReadDataset}.
     */
    public function iterate(): ReadDataset
    {
        return new ReadDataset($this->reader, $this->index);
    }

    /**
     * @return list<string>
     */
    public function mergedCells(): array
    {
        return $this->reader->mergedCells($this->index);
    }

    /**
     * Explicit column widths, keyed by column letter.
     *
     * @return array<string, float>
     */
    public function widths(): array
    {
        $widths = [];
        foreach ($this->reader->columnWidths($this->index) as $column => $width) {
            $widths[ColumnRef::letters($column)] = $width;
        }

        return $widths;
    }

    /**
     * @return array<string, string> cell reference => URL
     */
    public function links(): array
    {
        return $this->reader->hyperlinks($this->index);
    }

    /**
     * @return array<string, string> cell reference => comment text
     */
    public function comments(): array
    {
        return $this->reader->comments($this->index);
    }

    /**
     * @return array<string, string> cell reference => formula
     */
    public function formulas(): array
    {
        return $this->reader->formulas($this->index);
    }

    public function styleOf(Cell $cell): ?Style
    {
        return $this->reader->style($cell->styleId);
    }
}
