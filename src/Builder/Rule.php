<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Feature\Validation\DataValidationNode;
use PHPdot\Sheets\Engine\Feature\Validation\ValidationOperator;
use PHPdot\Sheets\Engine\Feature\Validation\ValidationType;
use PHPdot\Sheets\Engine\Support\ExcelDate;
use PHPdot\Sheets\Engine\Support\RuntimeException;

/**
 * A data-validation rule — returned by {@see Sheet::validate()},
 * {@see Sheet::dropdown()} and {@see Sheet::dropdownFrom()}. Pick a type
 * (`wholeNumber`, `date`, `time`, `custom`, …), constrain it (`between`,
 * `greaterThan`, the date-friendly `onOrAfter`, …), and optionally add a
 * `prompt`/`error` or mark it `required`. Closes the engine's missing Time factory.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class Rule implements FeatureBuilder
{
    private ?ValidationOperator $operator = null;
    private ?string $formula1 = null;
    private ?string $formula2 = null;
    private bool $allowBlank = true;
    private ?string $promptTitle = null;
    private ?string $prompt = null;
    private ?string $errorTitle = null;
    private ?string $error = null;

    /**
     * @param list<string> $values inline dropdown values (List type)
     */
    public function __construct(
        private readonly string $range,
        private ?ValidationType $type = null,
        private array $values = [],
        ?string $listRange = null,
    ) {
        $this->formula1 = $listRange;
    }

    public function wholeNumber(): self
    {
        $this->type = ValidationType::WholeNumber;

        return $this;
    }

    public function decimal(): self
    {
        $this->type = ValidationType::Decimal;

        return $this;
    }

    public function date(): self
    {
        $this->type = ValidationType::Date;

        return $this;
    }

    public function time(): self
    {
        $this->type = ValidationType::Time;

        return $this;
    }

    public function textLength(): self
    {
        $this->type = ValidationType::TextLength;

        return $this;
    }

    public function custom(string $formula): self
    {
        $this->type = ValidationType::Custom;
        $this->formula1 = $formula;

        return $this;
    }

    public function between(int|float|string|\DateTimeInterface $low, int|float|string|\DateTimeInterface $high): self
    {
        return $this->span(ValidationOperator::Between, $low, $high);
    }

    public function notBetween(int|float|string|\DateTimeInterface $low, int|float|string|\DateTimeInterface $high): self
    {
        return $this->span(ValidationOperator::NotBetween, $low, $high);
    }

    public function equal(int|float|string|\DateTimeInterface $value): self
    {
        return $this->compare(ValidationOperator::Equal, $value);
    }

    public function notEqual(int|float|string|\DateTimeInterface $value): self
    {
        return $this->compare(ValidationOperator::NotEqual, $value);
    }

    public function greaterThan(int|float|string|\DateTimeInterface $value): self
    {
        return $this->compare(ValidationOperator::GreaterThan, $value);
    }

    public function lessThan(int|float|string|\DateTimeInterface $value): self
    {
        return $this->compare(ValidationOperator::LessThan, $value);
    }

    public function greaterThanOrEqual(int|float|string|\DateTimeInterface $value): self
    {
        return $this->compare(ValidationOperator::GreaterThanOrEqual, $value);
    }

    public function lessThanOrEqual(int|float|string|\DateTimeInterface $value): self
    {
        return $this->compare(ValidationOperator::LessThanOrEqual, $value);
    }

    public function after(int|float|string|\DateTimeInterface $value): self
    {
        return $this->greaterThan($value);
    }

    public function before(int|float|string|\DateTimeInterface $value): self
    {
        return $this->lessThan($value);
    }

    public function onOrAfter(int|float|string|\DateTimeInterface $value): self
    {
        return $this->greaterThanOrEqual($value);
    }

    public function onOrBefore(int|float|string|\DateTimeInterface $value): self
    {
        return $this->lessThanOrEqual($value);
    }

    public function prompt(string $title, string $text): self
    {
        $this->promptTitle = $title;
        $this->prompt = $text;

        return $this;
    }

    public function error(string $title, string $text): self
    {
        $this->errorTitle = $title;
        $this->error = $text;

        return $this;
    }

    public function required(): self
    {
        $this->allowBlank = false;

        return $this;
    }

    public function toFeatureNode(): FeatureNode
    {
        if ($this->type === null) {
            throw new RuntimeException('Validation needs a type, e.g. ->wholeNumber(), ->date(), or a dropdown.');
        }

        return new DataValidationNode(
            $this->range,
            $this->type,
            $this->operator,
            $this->formula1,
            $this->formula2,
            $this->values,
            $this->allowBlank,
            $this->promptTitle,
            $this->prompt,
            $this->errorTitle,
            $this->error,
        );
    }

    private function compare(ValidationOperator $operator, int|float|string|\DateTimeInterface $value): self
    {
        $this->operator = $operator;
        $this->formula1 = $this->formulaFor($value);
        $this->formula2 = null;

        return $this;
    }

    private function span(
        ValidationOperator $operator,
        int|float|string|\DateTimeInterface $low,
        int|float|string|\DateTimeInterface $high,
    ): self {
        $this->operator = $operator;
        $this->formula1 = $this->formulaFor($low);
        $this->formula2 = $this->formulaFor($high);

        return $this;
    }

    private function formulaFor(int|float|string|\DateTimeInterface $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return (string) ExcelDate::toSerial($value);
        }
        if (is_string($value) && ($this->type === ValidationType::Date || $this->type === ValidationType::Time)) {
            try {
                return (string) ExcelDate::toSerial(new \DateTimeImmutable($value));
            } catch (\DateMalformedStringException) {
                return $value;
            }
        }

        return (string) $value;
    }
}
