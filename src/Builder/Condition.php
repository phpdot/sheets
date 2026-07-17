<?php

declare(strict_types=1);

/**
 * A cell-is conditional-formatting rule — returned by {@see Sheet::highlight()}.
 * Choose a comparison (`greaterThan`, `between`, …), then `->fill($style)`.
 * Numbers go into the rule verbatim; string literals are quoted.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Feature\Validation\CfOperator;
use PHPdot\Sheets\Engine\Feature\Validation\ConditionalFormatNode;
use PHPdot\Sheets\Engine\Model\Style;
use PHPdot\Sheets\Engine\Support\RuntimeException;

final class Condition implements FeatureBuilder
{
    private ?CfOperator $operator = null;
    private string $formula = '';
    private ?string $formula2 = null;
    private ?Style $style = null;

    /**
     * Starts a conditional-format rule scoped to the given cell range.
     *
     * @param string $range
     */
    public function __construct(private readonly string $range) {}

    /**
     * Highlights cells greater than the value.
     *
     * @param int|float|string $value
     *
     * @return self
     */
    public function greaterThan(int|float|string $value): self
    {
        return $this->compare(CfOperator::GreaterThan, $value);
    }

    /**
     * Highlights cells less than the value.
     *
     * @param int|float|string $value
     *
     * @return self
     */
    public function lessThan(int|float|string $value): self
    {
        return $this->compare(CfOperator::LessThan, $value);
    }

    /**
     * Highlights cells greater than or equal to the value.
     *
     * @param int|float|string $value
     *
     * @return self
     */
    public function greaterThanOrEqual(int|float|string $value): self
    {
        return $this->compare(CfOperator::GreaterThanOrEqual, $value);
    }

    /**
     * Highlights cells less than or equal to the value.
     *
     * @param int|float|string $value
     *
     * @return self
     */
    public function lessThanOrEqual(int|float|string $value): self
    {
        return $this->compare(CfOperator::LessThanOrEqual, $value);
    }

    /**
     * Highlights cells equal to the value.
     *
     * @param int|float|string $value
     *
     * @return self
     */
    public function equal(int|float|string $value): self
    {
        return $this->compare(CfOperator::Equal, $value);
    }

    /**
     * Highlights cells not equal to the value.
     *
     * @param int|float|string $value
     *
     * @return self
     */
    public function notEqual(int|float|string $value): self
    {
        return $this->compare(CfOperator::NotEqual, $value);
    }

    /**
     * Highlights cells between the two bounds (inclusive).
     *
     * @param int|float|string $low
     * @param int|float|string $high
     *
     * @return self
     */
    public function between(int|float|string $low, int|float|string $high): self
    {
        return $this->span(CfOperator::Between, $low, $high);
    }

    /**
     * Highlights cells outside the two bounds.
     *
     * @param int|float|string $low
     * @param int|float|string $high
     *
     * @return self
     */
    public function notBetween(int|float|string $low, int|float|string $high): self
    {
        return $this->span(CfOperator::NotBetween, $low, $high);
    }

    /**
     * Sets the style applied to matching cells.
     *
     * @param Style $style
     *
     * @return self
     */
    public function fill(Style $style): self
    {
        $this->style = $style;

        return $this;
    }

    public function toFeatureNode(): FeatureNode
    {
        if ($this->operator === null) {
            throw new RuntimeException('Conditional format needs a comparison, e.g. ->greaterThan(1000).');
        }
        if ($this->style === null) {
            throw new RuntimeException('Conditional format needs ->fill($style).');
        }

        return new ConditionalFormatNode($this->range, $this->operator, $this->formula, $this->style, $this->formula2);
    }

    /**
     * Records a single-value comparison operator and its formula.
     *
     * @param CfOperator $operator
     * @param int|float|string $value
     *
     * @return self
     */
    private function compare(CfOperator $operator, int|float|string $value): self
    {
        $this->operator = $operator;
        $this->formula = $this->formulaFor($value);
        $this->formula2 = null;

        return $this;
    }

    /**
     * Records a two-value (between) operator and its low/high bounds.
     *
     * @param CfOperator $operator
     * @param int|float|string $low
     * @param int|float|string $high
     *
     * @return self
     */
    private function span(CfOperator $operator, int|float|string $low, int|float|string $high): self
    {
        $this->operator = $operator;
        $this->formula = $this->formulaFor($low);
        $this->formula2 = $this->formulaFor($high);

        return $this;
    }

    /**
     * Renders a value as a rule formula, quoting and escaping strings.
     *
     * @param int|float|string $value
     *
     * @return string
     */
    private function formulaFor(int|float|string $value): string
    {
        return is_string($value) ? '"' . str_replace('"', '""', $value) . '"' : (string) $value;
    }
}
