<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Model;

use PHPdot\Sheets\Engine\Support\ExcelDate;

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
final class Cell
{
    public function __construct(
        public readonly int|float|string|bool|null $value,
        public readonly CellType $type = CellType::String,
        public readonly ?int $styleId = null,
    ) {}

    /**
     * The logical type as a string ("string", "number", "date", "bool",
     * "formula", "inline", "error") — inspect a cell without importing CellType.
     */
    public function type(): string
    {
        return $this->type->value;
    }

    public function isString(): bool
    {
        return $this->type === CellType::String;
    }

    public function isNumber(): bool
    {
        return $this->type === CellType::Number;
    }

    public function isDate(): bool
    {
        return $this->type === CellType::Date;
    }

    public function isBool(): bool
    {
        return $this->type === CellType::Bool;
    }

    public function isFormula(): bool
    {
        return $this->type === CellType::Formula;
    }

    public function isInline(): bool
    {
        return $this->type === CellType::Inline;
    }

    public function isError(): bool
    {
        return $this->type === CellType::Error;
    }

    /**
     * The cell as a DateTimeImmutable when it is a date (serials are 1900-system),
     * otherwise null.
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
     */
    public function withStyleId(?int $styleId): self
    {
        return new self($this->value, $this->type, $styleId);
    }

    /**
     * Return a copy with a different value and type. The original is never mutated.
     */
    public function withValue(int|float|string|bool|null $value, CellType $type = CellType::String): self
    {
        return new self($value, $type, $this->styleId);
    }
}
