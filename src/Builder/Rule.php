<?php

declare(strict_types=1);

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

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Feature\Validation\DataValidationNode;
use PHPdot\Sheets\Engine\Feature\Validation\ValidationOperator;
use PHPdot\Sheets\Engine\Feature\Validation\ValidationType;
use PHPdot\Sheets\Engine\Support\ExcelDate;
use PHPdot\Sheets\Engine\Support\RuntimeException;

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
     * Starts a validation rule over the given range, with an optional type and dropdown values.
     *
     * @param list<string> $values inline dropdown values (List type)
     * @param string $range
     * @param ?ValidationType $type
     * @param ?string $listRange
     */
    public function __construct(
        private readonly string $range,
        private ?ValidationType $type = null,
        private array $values = [],
        ?string $listRange = null,
    ) {
        $this->formula1 = $listRange;
    }

    /**
     * Restricts the cell to whole numbers.
     *
     * @return self
     */
    public function wholeNumber(): self
    {
        $this->type = ValidationType::WholeNumber;

        return $this;
    }

    /**
     * Restricts the cell to decimal numbers.
     *
     * @return self
     */
    public function decimal(): self
    {
        $this->type = ValidationType::Decimal;

        return $this;
    }

    /**
     * Restricts the cell to dates.
     *
     * @return self
     */
    public function date(): self
    {
        $this->type = ValidationType::Date;

        return $this;
    }

    /**
     * Restricts the cell to times.
     *
     * @return self
     */
    public function time(): self
    {
        $this->type = ValidationType::Time;

        return $this;
    }

    /**
     * Restricts the cell by text length.
     *
     * @return self
     */
    public function textLength(): self
    {
        $this->type = ValidationType::TextLength;

        return $this;
    }

    /**
     * Restricts the cell with a custom formula.
     *
     * @param string $formula
     *
     * @return self
     */
    public function custom(string $formula): self
    {
        $this->type = ValidationType::Custom;
        $this->formula1 = $formula;

        return $this;
    }

    /**
     * Requires the value to fall between the two bounds (inclusive).
     *
     * @param int|float|string|\DateTimeInterface $low
     * @param int|float|string|\DateTimeInterface $high
     *
     * @return self
     */
    public function between(int|float|string|\DateTimeInterface $low, int|float|string|\DateTimeInterface $high): self
    {
        return $this->span(ValidationOperator::Between, $low, $high);
    }

    /**
     * Requires the value to fall outside the two bounds.
     *
     * @param int|float|string|\DateTimeInterface $low
     * @param int|float|string|\DateTimeInterface $high
     *
     * @return self
     */
    public function notBetween(int|float|string|\DateTimeInterface $low, int|float|string|\DateTimeInterface $high): self
    {
        return $this->span(ValidationOperator::NotBetween, $low, $high);
    }

    /**
     * Requires the value to equal the given value.
     *
     * @param int|float|string|\DateTimeInterface $value
     *
     * @return self
     */
    public function equal(int|float|string|\DateTimeInterface $value): self
    {
        return $this->compare(ValidationOperator::Equal, $value);
    }

    /**
     * Requires the value to differ from the given value.
     *
     * @param int|float|string|\DateTimeInterface $value
     *
     * @return self
     */
    public function notEqual(int|float|string|\DateTimeInterface $value): self
    {
        return $this->compare(ValidationOperator::NotEqual, $value);
    }

    /**
     * Requires the value to be greater than the given value.
     *
     * @param int|float|string|\DateTimeInterface $value
     *
     * @return self
     */
    public function greaterThan(int|float|string|\DateTimeInterface $value): self
    {
        return $this->compare(ValidationOperator::GreaterThan, $value);
    }

    /**
     * Requires the value to be less than the given value.
     *
     * @param int|float|string|\DateTimeInterface $value
     *
     * @return self
     */
    public function lessThan(int|float|string|\DateTimeInterface $value): self
    {
        return $this->compare(ValidationOperator::LessThan, $value);
    }

    /**
     * Requires the value to be greater than or equal to the given value.
     *
     * @param int|float|string|\DateTimeInterface $value
     *
     * @return self
     */
    public function greaterThanOrEqual(int|float|string|\DateTimeInterface $value): self
    {
        return $this->compare(ValidationOperator::GreaterThanOrEqual, $value);
    }

    /**
     * Requires the value to be less than or equal to the given value.
     *
     * @param int|float|string|\DateTimeInterface $value
     *
     * @return self
     */
    public function lessThanOrEqual(int|float|string|\DateTimeInterface $value): self
    {
        return $this->compare(ValidationOperator::LessThanOrEqual, $value);
    }

    /**
     * Requires a date/time after the given value (alias of greaterThan).
     *
     * @param int|float|string|\DateTimeInterface $value
     *
     * @return self
     */
    public function after(int|float|string|\DateTimeInterface $value): self
    {
        return $this->greaterThan($value);
    }

    /**
     * Requires a date/time before the given value (alias of lessThan).
     *
     * @param int|float|string|\DateTimeInterface $value
     *
     * @return self
     */
    public function before(int|float|string|\DateTimeInterface $value): self
    {
        return $this->lessThan($value);
    }

    /**
     * Requires a date/time on or after the given value (alias of greaterThanOrEqual).
     *
     * @param int|float|string|\DateTimeInterface $value
     *
     * @return self
     */
    public function onOrAfter(int|float|string|\DateTimeInterface $value): self
    {
        return $this->greaterThanOrEqual($value);
    }

    /**
     * Requires a date/time on or before the given value (alias of lessThanOrEqual).
     *
     * @param int|float|string|\DateTimeInterface $value
     *
     * @return self
     */
    public function onOrBefore(int|float|string|\DateTimeInterface $value): self
    {
        return $this->lessThanOrEqual($value);
    }

    /**
     * Sets the input-prompt title and text shown when the cell is selected.
     *
     * @param string $title
     * @param string $text
     *
     * @return self
     */
    public function prompt(string $title, string $text): self
    {
        $this->promptTitle = $title;
        $this->prompt = $text;

        return $this;
    }

    /**
     * Sets the error-alert title and text shown on invalid input.
     *
     * @param string $title
     * @param string $text
     *
     * @return self
     */
    public function error(string $title, string $text): self
    {
        $this->errorTitle = $title;
        $this->error = $text;

        return $this;
    }

    /**
     * Rejects blank values.
     *
     * @return self
     */
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

    /**
     * Records a single-value operator and its formula.
     *
     * @param ValidationOperator $operator
     * @param int|float|string|\DateTimeInterface $value
     *
     * @return self
     */
    private function compare(ValidationOperator $operator, int|float|string|\DateTimeInterface $value): self
    {
        $this->operator = $operator;
        $this->formula1 = $this->formulaFor($value);
        $this->formula2 = null;

        return $this;
    }

    /**
     * Records a two-value (between) operator and its low/high formulas.
     *
     * @param ValidationOperator $operator
     * @param int|float|string|\DateTimeInterface $low
     * @param int|float|string|\DateTimeInterface $high
     *
     * @return self
     */
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

    /**
     * Renders a value as a validation formula, serialising dates and times to Excel serials.
     *
     * @param int|float|string|\DateTimeInterface $value
     *
     * @return string
     */
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
