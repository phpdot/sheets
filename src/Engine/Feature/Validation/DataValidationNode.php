<?php

declare(strict_types=1);

/**
 * A data validation applied to `sqref` — a dropdown list, a numeric/date/time
 * range, a text-length limit, or a custom formula — optionally with an input
 * prompt and an error alert. Use the static factories for ergonomic construction.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Engine\Feature\Validation;

use PHPdot\Sheets\Engine\Feature\Capability;
use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Support\InvalidArgumentException;

final class DataValidationNode implements FeatureNode
{
    /**
     * Builds a validation for the cells in `$sqref`; rejects inline List values
     * that contain a comma, which Excel's inline-list format cannot represent.
     *
     * @param list<string> $values inline allowed values (List type)
     * @param string $sqref
     * @param ValidationType $type
     * @param ?ValidationOperator $operator
     * @param ?string $formula1
     * @param ?string $formula2
     * @param bool $allowBlank
     * @param ?string $promptTitle
     * @param ?string $prompt
     * @param ?string $errorTitle
     * @param ?string $error
     *
     * @throws InvalidArgumentException When a List value contains a comma — the
     *                                  inline-list format uses the comma as its separator with no escape,
     *                                  so use a range-backed list ({@see self::listFromRange()}) instead.
     */
    public function __construct(
        public readonly string $sqref,
        public readonly ValidationType $type,
        public readonly ?ValidationOperator $operator = null,
        public readonly ?string $formula1 = null,
        public readonly ?string $formula2 = null,
        public readonly array $values = [],
        public readonly bool $allowBlank = true,
        public readonly ?string $promptTitle = null,
        public readonly ?string $prompt = null,
        public readonly ?string $errorTitle = null,
        public readonly ?string $error = null,
    ) {
        if ($this->type === ValidationType::List) {
            foreach ($this->values as $value) {
                if (str_contains($value, ',')) {
                    throw new InvalidArgumentException(sprintf(
                        'Inline list value "%s" contains a comma, which Excel cannot represent'
                        . ' in an inline list — put the values in cells and use listFromRange().',
                        $value,
                    ));
                }
            }
        }
    }

    public function capability(): Capability
    {
        return Capability::DataValidation;
    }

    /**
     * A dropdown of inline values.
     *
     * @param list<string> $values
     * @param string $sqref
     * @param bool $allowBlank
     *
     * @return self
     */
    public static function list(string $sqref, array $values, bool $allowBlank = true): self
    {
        return new self($sqref, ValidationType::List, values: $values, allowBlank: $allowBlank);
    }

    /**
     * A dropdown sourced from a worksheet range (e.g. "Sheet1!$A$1:$A$9").
     *
     * @param string $range
     * @param string $sqref
     * @param bool $allowBlank
     *
     * @return DataValidationNode
     */
    public static function listFromRange(string $sqref, string $range, bool $allowBlank = true): self
    {
        return new self($sqref, ValidationType::List, formula1: $range, allowBlank: $allowBlank);
    }

    /**
     * A validation constraining the cell to a whole number matching the operator and bound(s).
     *
     * @param string $sqref
     * @param ValidationOperator $operator
     * @param string $formula1
     * @param ?string $formula2
     * @param bool $allowBlank
     *
     * @return self
     */
    public static function wholeNumber(string $sqref, ValidationOperator $operator, string $formula1, ?string $formula2 = null, bool $allowBlank = true): self
    {
        return new self($sqref, ValidationType::WholeNumber, $operator, $formula1, $formula2, allowBlank: $allowBlank);
    }

    /**
     * A validation constraining the cell to a decimal number matching the operator and bound(s).
     *
     * @param string $sqref
     * @param ValidationOperator $operator
     * @param string $formula1
     * @param ?string $formula2
     * @param bool $allowBlank
     *
     * @return self
     */
    public static function decimal(string $sqref, ValidationOperator $operator, string $formula1, ?string $formula2 = null, bool $allowBlank = true): self
    {
        return new self($sqref, ValidationType::Decimal, $operator, $formula1, $formula2, allowBlank: $allowBlank);
    }

    /**
     * A validation constraining the cell to a date matching the operator and bound(s).
     *
     * @param string $sqref
     * @param ValidationOperator $operator
     * @param string $formula1
     * @param ?string $formula2
     * @param bool $allowBlank
     *
     * @return self
     */
    public static function date(string $sqref, ValidationOperator $operator, string $formula1, ?string $formula2 = null, bool $allowBlank = true): self
    {
        return new self($sqref, ValidationType::Date, $operator, $formula1, $formula2, allowBlank: $allowBlank);
    }

    /**
     * A validation constraining the entered text length via the operator and bound(s).
     *
     * @param string $sqref
     * @param ValidationOperator $operator
     * @param string $formula1
     * @param ?string $formula2
     * @param bool $allowBlank
     *
     * @return self
     */
    public static function textLength(string $sqref, ValidationOperator $operator, string $formula1, ?string $formula2 = null, bool $allowBlank = true): self
    {
        return new self($sqref, ValidationType::TextLength, $operator, $formula1, $formula2, allowBlank: $allowBlank);
    }

    /**
     * A validation that passes only when the given formula evaluates to true for the cell.
     *
     * @param string $sqref
     * @param string $formula
     * @param bool $allowBlank
     *
     * @return self
     */
    public static function custom(string $sqref, string $formula, bool $allowBlank = true): self
    {
        return new self($sqref, ValidationType::Custom, formula1: $formula, allowBlank: $allowBlank);
    }

    /**
     * Show an input prompt (tooltip) when a cell in the range is selected.
     *
     * @param string $title
     * @param string $text
     *
     * @return DataValidationNode
     */
    public function withInputMessage(string $title, string $text): self
    {
        return new self(
            $this->sqref,
            $this->type,
            $this->operator,
            $this->formula1,
            $this->formula2,
            $this->values,
            $this->allowBlank,
            $title,
            $text,
            $this->errorTitle,
            $this->error,
        );
    }

    /**
     * Customize the error alert shown when an invalid value is entered.
     *
     * @param string $title
     * @param string $text
     *
     * @return DataValidationNode
     */
    public function withErrorMessage(string $title, string $text): self
    {
        return new self(
            $this->sqref,
            $this->type,
            $this->operator,
            $this->formula1,
            $this->formula2,
            $this->values,
            $this->allowBlank,
            $this->promptTitle,
            $this->prompt,
            $title,
            $text,
        );
    }
}
