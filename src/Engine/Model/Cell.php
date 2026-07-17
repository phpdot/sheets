<?php

declare(strict_types=1);

/**
 * An immutable spreadsheet cell — the rich-path value object.
 *
 * The bulk write path uses raw scalar arrays and never allocates a Cell per
 * cell; this type exists for the ergonomic rich API and for read results.
 * `style` is an integer index into a writer's style table (registered once,
 * referenced in O(1)), not a Style object per cell.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Model;

use PHPdot\Sheets\Engine\Support\ExcelDate;

final class Cell
{
    /**
     * Wraps a value with its logical cell type and an optional registered style id.
     *
     * @param int|float|string|bool|null $value
     * @param CellType $type
     * @param ?int $styleId
     */
    public function __construct(
        public readonly int|float|string|bool|null $value,
        public readonly CellType $type = CellType::String,
        public readonly ?int $styleId = null,
    ) {}

    /**
     * The logical type as a string ("string", "number", "date", "bool",
     * "formula", "inline", "error") — inspect a cell without importing CellType.
     *
     * @return string
     */
    public function type(): string
    {
        return $this->type->value;
    }

    /**
     * Whether the cell holds a string value.
     *
     * @return bool
     */
    public function isString(): bool
    {
        return $this->type === CellType::String;
    }

    /**
     * Whether the cell holds a numeric value.
     *
     * @return bool
     */
    public function isNumber(): bool
    {
        return $this->type === CellType::Number;
    }

    /**
     * Whether the cell holds a date (an Excel serial to be read via toDateTime()).
     *
     * @return bool
     */
    public function isDate(): bool
    {
        return $this->type === CellType::Date;
    }

    /**
     * Whether the cell holds a boolean value.
     *
     * @return bool
     */
    public function isBool(): bool
    {
        return $this->type === CellType::Bool;
    }

    /**
     * Whether the cell holds a formula expression.
     *
     * @return bool
     */
    public function isFormula(): bool
    {
        return $this->type === CellType::Formula;
    }

    /**
     * Whether the cell holds an inline (non-shared) string.
     *
     * @return bool
     */
    public function isInline(): bool
    {
        return $this->type === CellType::Inline;
    }

    /**
     * Whether the cell holds an Excel error value.
     *
     * @return bool
     */
    public function isError(): bool
    {
        return $this->type === CellType::Error;
    }

    /**
     * The cell as a DateTimeImmutable when it is a date (serials are 1900-system),
     * otherwise null.
     *
     * @return ?\DateTimeImmutable
     */
    public function toDateTime(): ?\DateTimeImmutable
    {
        if ($this->type !== CellType::Date || !(is_int($this->value) || is_float($this->value))) {
            return null;
        }

        return ExcelDate::toDateTime((float) $this->value);
    }

    /**
     * Return a copy with a different style id. The original is never mutated.
     *
     * @param ?int $styleId
     *
     * @return Cell
     */
    public function withStyleId(?int $styleId): self
    {
        return new self($this->value, $this->type, $styleId);
    }

    /**
     * Return a copy with a different value and type. The original is never mutated.
     *
     * @param string|int|float|bool|null $value
     * @param CellType $type
     *
     * @return Cell
     */
    public function withValue(int|float|string|bool|null $value, CellType $type = CellType::String): self
    {
        return new self($value, $type, $this->styleId);
    }
}
