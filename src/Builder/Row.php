<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Model\Row as RowValue;
use PHPdot\Sheets\Engine\Model\Style;
use PHPdot\Sheets\Engine\Support\RuntimeException;
use PHPdot\Sheets\Engine\Xlsx\Writer;

/**
 * The "current" row handed back by {@see Sheet::addRow()}, decorated in place
 * until it flushes. Scalars stream through the engine fast path; the first
 * `addCell()` upgrades the row to the rich path (per-cell types and styles).
 *
 * Once flushed (the next row was added, or the workbook saved) the row is sealed:
 * decorating it then throws, because its bytes are already on disk.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class Row
{
    /** @var list<Cell>|null Rich cells once `addCell()` is used; null = scalar fast path. */
    private ?array $cells = null;
    private Style|int|null $style = null;
    private ?float $height = null;
    private bool $hidden = false;
    private bool $sealed = false;

    /**
     * @param list<int|float|string|bool|null> $values
     */
    public function __construct(private readonly array $values) {}

    /**
     * Add a rich cell and return it to decorate (type/style/format). The first
     * call upgrades the row off the fast path: values passed to `addRow()` are
     * kept as inferred cells, then this one is appended.
     */
    public function addCell(int|float|string|bool|null|\DateTimeInterface $value): Cell
    {
        $this->assertOpen();

        if ($this->cells === null) {
            $this->cells = [];
            foreach ($this->values as $scalar) {
                $this->cells[] = new Cell($scalar);
            }
        }

        $cell = new Cell($value);
        $this->cells[] = $cell;

        return $cell;
    }

    /**
     * Apply a style to the whole row. Accepts a {@see Style} (resolved internally)
     * or a pre-registered int id for tight loops.
     */
    public function style(Style|int $style): self
    {
        $this->assertOpen();
        $this->style = $style;

        return $this;
    }

    public function height(float $points): self
    {
        $this->assertOpen();
        $this->height = $points;

        return $this;
    }

    public function hide(): self
    {
        $this->assertOpen();
        $this->hidden = true;

        return $this;
    }

    /**
     * Write this row through the engine — scalar fast path, or the rich object
     * path once cells are present — and seal it.
     *
     * @internal Called by {@see Sheet} when the row flushes.
     */
    public function writeTo(Writer $writer, int $rowNumber): void
    {
        if ($this->cells === null) {
            $writer->addRow($this->values, $this->resolveStyleId($writer), $this->height, $this->hidden);
        } else {
            $cells = [];
            foreach ($this->cells as $cell) {
                $cells[] = $cell->toCellValue($writer, $rowNumber);
            }
            $writer->addRowObject(new RowValue($cells, $this->resolveStyleId($writer), $this->height, $this->hidden));
        }

        $this->sealed = true;
    }

    private function resolveStyleId(Writer $writer): ?int
    {
        return match (true) {
            $this->style === null => null,
            $this->style instanceof Style => $writer->registerStyle($this->style),
            default => $this->style,
        };
    }

    private function assertOpen(): void
    {
        if ($this->sealed) {
            throw new RuntimeException(
                'This row is already written; decorate it before the next addRow() or save().',
            );
        }
    }
}
