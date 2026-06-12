<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Model\Cell as CellValue;
use PHPdot\Sheets\Engine\Model\CellType;
use PHPdot\Sheets\Engine\Model\NumberFormats;
use PHPdot\Sheets\Engine\Model\Style;
use PHPdot\Sheets\Engine\Support\ExcelDate;
use PHPdot\Sheets\Engine\Support\InvalidArgumentException;
use PHPdot\Sheets\Engine\Support\RuntimeException;
use PHPdot\Sheets\Engine\Xlsx\Writer;

/**
 * A single rich cell handed back by {@see Row::addCell()}. Its type is inferred
 * from the value by default; the developer can choose it (`asDate()`,
 * `asFormula()`, …) and decorate it (`style()`, `format()`). Resolved to the
 * engine's immutable cell — with style and number format registered — when its
 * row flushes; decorating it after that throws.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class Cell
{
    private ?CellType $type = null;
    private Style|int|null $style = null;
    private ?string $format = null;
    private bool $sealed = false;

    public function __construct(
        private readonly int|float|string|bool|null|\DateTimeInterface $value,
    ) {}

    public function asText(): self
    {
        return $this->asType(CellType::String);
    }

    public function asNumber(): self
    {
        return $this->asType(CellType::Number);
    }

    public function asDate(): self
    {
        return $this->asType(CellType::Date);
    }

    public function asFormula(): self
    {
        return $this->asType(CellType::Formula);
    }

    public function asBool(): self
    {
        return $this->asType(CellType::Bool);
    }

    public function asError(): self
    {
        return $this->asType(CellType::Error);
    }

    public function asInline(): self
    {
        return $this->asType(CellType::Inline);
    }

    public function style(Style|int $style): self
    {
        $this->assertOpen();
        $this->style = $style;

        return $this;
    }

    /**
     * Number format: a preset name ("currency", "percent", "date", …) or a raw
     * Excel format code ("$#,##0.00").
     */
    public function format(string $code): self
    {
        $this->assertOpen();
        $this->format = NumberFormats::resolve($code);

        return $this;
    }

    /**
     * Resolve to the engine cell, registering any style / number format. Seals the cell.
     *
     * @internal Called by {@see Row} when the row flushes.
     */
    public function toCellValue(Writer $writer, int $rowNumber): CellValue
    {
        $type = $this->type ?? $this->infer();
        $cell = new CellValue($this->prepareValue($type, $rowNumber), $type, $this->resolveStyle($writer, $type));
        $this->sealed = true;

        return $cell;
    }

    private function asType(CellType $type): self
    {
        $this->assertOpen();
        $this->type = $type;

        return $this;
    }

    private function infer(): CellType
    {
        return match (true) {
            $this->value instanceof \DateTimeInterface => CellType::Date,
            is_bool($this->value) => CellType::Bool,
            is_int($this->value) || is_float($this->value) => CellType::Number,
            default => CellType::String,
        };
    }

    private function prepareValue(CellType $type, int $rowNumber): int|float|string|bool|null
    {
        $value = $this->value;

        if ($type === CellType::Date) {
            if ($value instanceof \DateTimeInterface) {
                return ExcelDate::toSerial($value);
            }
            if (is_int($value) || is_float($value)) {
                return (float) $value;
            }

            throw new InvalidArgumentException('asDate() expects a DateTimeInterface or a numeric serial.');
        }

        if ($value instanceof \DateTimeInterface) {
            throw new InvalidArgumentException('A date/time value must be written as a date — use asDate().');
        }

        if ($type === CellType::Formula) {
            if (!is_string($value)) {
                throw new InvalidArgumentException('asFormula() expects a string expression.');
            }

            $formula = str_starts_with($value, '=') ? substr($value, 1) : $value;

            return str_replace('{row}', (string) $rowNumber, $formula);
        }

        if ($type === CellType::Number && !is_int($value) && !is_float($value)) {
            if (is_string($value) && is_numeric($value)) {
                return str_contains($value, '.') || str_contains(strtolower($value), 'e')
                    ? (float) $value
                    : (int) $value;
            }

            throw new InvalidArgumentException('asNumber() expects a numeric value.');
        }

        return $value;
    }

    private function resolveStyle(Writer $writer, CellType $type): ?int
    {
        $format = $this->format;
        if ($format === null && $type === CellType::Date) {
            // A date with no number format would render as a raw serial number.
            $format = NumberFormats::DATE;
        }
        if ($this->style === null && $format === null) {
            return null;
        }
        if (is_int($this->style)) {
            return $this->style;
        }

        $style = $this->style ?? Style::make();
        if ($format !== null && $style->numberFormat === null) {
            $style = $style->withNumberFormat($format);
        }

        return $writer->registerStyle($style);
    }

    private function assertOpen(): void
    {
        if ($this->sealed) {
            throw new RuntimeException(
                'This cell is already written; decorate it before the next addRow() or save().',
            );
        }
    }
}
