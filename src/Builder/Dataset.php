<?php

declare(strict_types=1);

/**
 * Writes an iterable of associative rows (a DB result) to a sheet — returned by
 * {@see Sheet::iterate()}. Map keys to header labels with `columns()`, transform a
 * field with `cast()` or the whole row with `map()`, number-format columns with
 * `format()`, style the header, and place the block with `startAt()`. `write()`
 * streams the source one row at a time, so a generator or DB cursor exports at O(1).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Model\Style;
use PHPdot\Sheets\Engine\Support\InvalidArgumentException;

final class Dataset
{
    use CellAnchor;

    /**
     * @var array<int|string, string>|null Source key => header label (also selects and orders).
     */

    private ?array $columns = null;

    /**
     * @var array<int|string, \Closure> Source key => per-field transform.
     */

    private array $casts = [];

    /**
     * @var array<int|string, string> Source key => number-format code/preset.
     */

    private array $formats = [];

    private ?\Closure $map = null;
    private ?Style $headerStyle = null;
    private string $startAt = 'A1';

    /**
     * Starts a dataset export over the given sheet and its iterable of associative rows.
     *
     * @param iterable<array<int|string, mixed>> $rows
     * @param Sheet $sheet
     */
    public function __construct(
        private readonly Sheet $sheet,
        private readonly iterable $rows,
    ) {}

    /**
     * Select, order and label columns by mapping each source key to a header label.
     *
     * @param array<int|string, string> $columns
     *
     * @return self
     */
    public function columns(array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Transform one field as it becomes a cell. The callback receives the value
     * and the whole row; its result may be a scalar or a DateTimeInterface.
     *
     * @param int|string $field
     * @param callable $fn
     *
     * @return Dataset
     */
    public function cast(int|string $field, callable $fn): self
    {
        $this->casts[$field] = $fn(...);

        return $this;
    }

    /**
     * Number-format columns by source key, e.g.
     * `format(['revenue' => 'currency', 'rate' => 'percent'])`. Values are preset
     * names or raw Excel format codes.
     *
     * @param array<int|string, string> $formats
     *
     * @return Dataset
     */
    public function format(array $formats): self
    {
        foreach ($formats as $key => $code) {
            $this->formats[$key] = $code;
        }

        return $this;
    }

    /**
     * Transform the whole row (derive columns, reshape, or return null to skip it).
     * Runs before column selection.
     *
     * @param callable $fn
     *
     * @return Dataset
     */
    public function map(callable $fn): self
    {
        $this->map = $fn(...);

        return $this;
    }

    /**
     * Sets the style applied to the header row.
     *
     * @param Style $style
     *
     * @return self
     */
    public function headerStyle(Style $style): self
    {
        $this->headerStyle = $style;

        return $this;
    }

    /**
     * Top-left A1 cell of the block (default "A1").
     *
     * @param string $cell
     *
     * @return Dataset
     */
    public function startAt(string $cell): self
    {
        $this->startAt = $cell;

        return $this;
    }

    /**
     * Streams the source rows to the sheet, writing the header once, one row at a time.
     *
     * @return void
     */
    public function write(): void
    {
        [$column, $rowOffset] = $this->parseCellRef($this->startAt);
        $prefix = array_fill(0, $column, null);
        for ($i = 0; $i < $rowOffset; $i++) {
            $this->sheet->addRow();
        }

        $keys = $this->columns !== null ? array_keys($this->columns) : null;
        $columnFormats = null;
        $headerWritten = false;

        foreach ($this->rows as $source) {
            if ($this->map !== null) {
                $mapped = ($this->map)($source);
                if ($mapped === null) {
                    continue;
                }
                if (!is_array($mapped)) {
                    throw new InvalidArgumentException('map() must return the row array, or null to skip it.');
                }
                $source = $mapped;
            }

            $keys ??= array_keys($source);
            $columnFormats ??= $this->columnFormatsFor($keys, $column);
            if (!$headerWritten) {
                $this->writeHeader($prefix, $keys);
                $headerWritten = true;
            }

            $values = [];
            foreach ($keys as $key) {
                $value = $source[$key] ?? null;
                if (isset($this->casts[$key])) {
                    $value = ($this->casts[$key])($value, $source);
                }
                $values[] = $value;
            }
            $this->writeRow([...$prefix, ...$values], $columnFormats);
        }

        if (!$headerWritten && $keys !== null) {
            $this->writeHeader($prefix, $keys);
        }
    }

    /**
     * Builds the per-output-column number-format list, with nulls for the prefix columns.
     *
     * @param list<int|string> $keys
     * @param int $prefixWidth
     *
     * @return list<string|null> format per output column (prefix columns are null)
     */
    private function columnFormatsFor(array $keys, int $prefixWidth): array
    {
        $formats = array_fill(0, $prefixWidth, null);
        foreach ($keys as $key) {
            $formats[] = $this->formats[$key] ?? null;
        }

        return $formats;
    }

    /**
     * Writes the header row (mapped labels or raw keys) and applies the header style.
     *
     * @param list<null> $prefix
     * @param list<int|string> $keys
     *
     * @return void
     */
    private function writeHeader(array $prefix, array $keys): void
    {
        $labels = $this->columns !== null ? array_values($this->columns) : $keys;
        $header = $this->sheet->addRow([...$prefix, ...$labels]);
        if ($this->headerStyle !== null) {
            $header->style($this->headerStyle);
        }
    }

    /**
     * Writes one data row, using per-cell rich output when a column has a format or a date.
     *
     * @param list<mixed> $values
     * @param list<string|null> $columnFormats
     *
     * @return void
     */
    private function writeRow(array $values, array $columnFormats): void
    {
        foreach ($columnFormats as $format) {
            if ($format !== null) {
                $this->writeRich($values, $columnFormats);

                return;
            }
        }

        $scalars = [];
        foreach ($values as $raw) {
            $value = $this->cellValue($raw);
            if ($value instanceof \DateTimeInterface) {
                $this->writeRich($values, $columnFormats);

                return;
            }
            $scalars[] = $value;
        }

        $this->sheet->addRow($scalars);
    }

    /**
     * Writes one row cell-by-cell, applying each column number format.
     *
     * @param list<mixed> $values
     * @param list<string|null> $columnFormats
     *
     * @return void
     */
    private function writeRich(array $values, array $columnFormats): void
    {
        $row = $this->sheet->addRow();
        foreach ($values as $i => $raw) {
            $cell = $row->addCell($this->cellValue($raw));
            $format = $columnFormats[$i] ?? null;
            if ($format !== null) {
                $cell->format($format);
            }
        }
    }

    /**
     * Validates that a raw value is a scalar, null, or DateTimeInterface a cell can hold.
     *
     * @param mixed $value
     *
     * @return int|float|string|bool|null|\DateTimeInterface
     */
    private function cellValue(mixed $value): int|float|string|bool|null|\DateTimeInterface
    {
        if (is_scalar($value) || $value === null || $value instanceof \DateTimeInterface) {
            return $value;
        }

        throw new InvalidArgumentException(sprintf(
            'Dataset value of type %s cannot be written; cast it to a scalar or DateTime first.',
            get_debug_type($value),
        ));
    }
}
