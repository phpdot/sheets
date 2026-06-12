<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Feature\Validation\CfOperator;
use PHPdot\Sheets\Engine\Feature\Validation\ConditionalFormatNode;
use PHPdot\Sheets\Engine\Model\Style;
use PHPdot\Sheets\Engine\Support\RuntimeException;

/**
 * A cell-is conditional-formatting rule — returned by {@see Sheet::highlight()}.
 * Choose a comparison (`greaterThan`, `between`, …), then `->fill($style)`.
 * Numbers go into the rule verbatim; string literals are quoted.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class Condition implements FeatureBuilder
{
    private ?CfOperator $operator = null;
    private string $formula = '';
    private ?string $formula2 = null;
    private ?Style $style = null;

    public function __construct(private readonly string $range) {}

    public function greaterThan(int|float|string $value): self
    {
        return $this->compare(CfOperator::GreaterThan, $value);
    }

    public function lessThan(int|float|string $value): self
    {
        return $this->compare(CfOperator::LessThan, $value);
    }

    public function greaterThanOrEqual(int|float|string $value): self
    {
        return $this->compare(CfOperator::GreaterThanOrEqual, $value);
    }

    public function lessThanOrEqual(int|float|string $value): self
    {
        return $this->compare(CfOperator::LessThanOrEqual, $value);
    }

    public function equal(int|float|string $value): self
    {
        return $this->compare(CfOperator::Equal, $value);
    }

    public function notEqual(int|float|string $value): self
    {
        return $this->compare(CfOperator::NotEqual, $value);
    }

    public function between(int|float|string $low, int|float|string $high): self
    {
        return $this->span(CfOperator::Between, $low, $high);
    }

    public function notBetween(int|float|string $low, int|float|string $high): self
    {
        return $this->span(CfOperator::NotBetween, $low, $high);
    }

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

    private function compare(CfOperator $operator, int|float|string $value): self
    {
        $this->operator = $operator;
        $this->formula = $this->formulaFor($value);
        $this->formula2 = null;

        return $this;
    }

    private function span(CfOperator $operator, int|float|string $low, int|float|string $high): self
    {
        $this->operator = $operator;
        $this->formula = $this->formulaFor($low);
        $this->formula2 = $this->formulaFor($high);

        return $this;
    }

    private function formulaFor(int|float|string $value): string
    {
        return is_string($value) ? '"' . str_replace('"', '""', $value) . '"' : (string) $value;
    }
}
