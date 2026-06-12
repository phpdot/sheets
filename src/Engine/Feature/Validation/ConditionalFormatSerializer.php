<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Feature\Validation;

use PHPdot\Sheets\Engine\Feature\Capability;
use PHPdot\Sheets\Engine\Feature\FeatureContext;
use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Feature\FeatureSerializer;
use PHPdot\Sheets\Engine\Feature\SheetTrailerOrder;
use PHPdot\Sheets\Engine\Support\Xml;

/**
 * Renders the conditional-formatting nodes — `cellIs` (with a differential
 * format), data bars, color scales, and icon sets — as `<conditionalFormatting>`
 * worksheet trailers. Depends only on core — never on the XLSX codec.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ConditionalFormatSerializer implements FeatureSerializer
{
    private int $priority = 0;

    public function capability(): Capability
    {
        return Capability::ConditionalFormatting;
    }

    public function serialize(FeatureNode $node, FeatureContext $context): void
    {
        if ($node instanceof ConditionalFormatNode) {
            $this->add($context, $node->sqref, $this->cellIsRule($node, $context));
        } elseif ($node instanceof DataBarNode) {
            $this->add($context, $node->sqref, $this->dataBarRule($node));
        } elseif ($node instanceof ColorScaleNode) {
            $this->add($context, $node->sqref, $this->colorScaleRule($node));
        } elseif ($node instanceof IconSetNode) {
            $this->add($context, $node->sqref, $this->iconSetRule($node));
        } elseif ($node instanceof ExpressionFormatNode) {
            $this->add($context, $node->sqref, $this->expressionRule($node, $context));
        } elseif ($node instanceof DuplicateValuesNode) {
            $this->add($context, $node->sqref, $this->duplicateRule($node, $context));
        }
    }

    private function add(FeatureContext $context, string $sqref, string $rule): void
    {
        $context->trailers->add(
            SheetTrailerOrder::CONDITIONAL_FORMATTING,
            '<conditionalFormatting sqref="' . Xml::attribute($sqref) . '">' . $rule . '</conditionalFormatting>',
        );
    }

    private function cellIsRule(ConditionalFormatNode $node, FeatureContext $context): string
    {
        $dxfId = $context->styles->registerDxf($node->style);
        $formulas = '<formula>' . Xml::text($node->formula) . '</formula>';
        if ($node->formula2 !== null) {
            $formulas .= '<formula>' . Xml::text($node->formula2) . '</formula>';
        }

        return '<cfRule type="cellIs" dxfId="' . $dxfId . '" priority="' . (++$this->priority) . '"'
            . ' operator="' . $node->operator->value . '">' . $formulas . '</cfRule>';
    }

    private function dataBarRule(DataBarNode $node): string
    {
        return '<cfRule type="dataBar" priority="' . (++$this->priority) . '"><dataBar>'
            . '<cfvo type="min"/><cfvo type="max"/><color rgb="FF' . $node->color->rgb . '"/></dataBar></cfRule>';
    }

    private function colorScaleRule(ColorScaleNode $node): string
    {
        $cfvo = '<cfvo type="min"/>';
        $colors = '<color rgb="FF' . $node->minColor->rgb . '"/>';
        if ($node->midColor !== null) {
            $cfvo .= '<cfvo type="percentile" val="50"/>';
            $colors .= '<color rgb="FF' . $node->midColor->rgb . '"/>';
        }
        $cfvo .= '<cfvo type="max"/>';
        $colors .= '<color rgb="FF' . $node->maxColor->rgb . '"/>';

        return '<cfRule type="colorScale" priority="' . (++$this->priority) . '"><colorScale>'
            . $cfvo . $colors . '</colorScale></cfRule>';
    }

    private function iconSetRule(IconSetNode $node): string
    {
        $count = (int) $node->iconSet->value[0];
        $thresholds = '';
        for ($i = 0; $i < $count; $i++) {
            $thresholds .= '<cfvo type="percent" val="' . (int) round(100 * $i / $count) . '"/>';
        }

        return '<cfRule type="iconSet" priority="' . (++$this->priority) . '"><iconSet iconSet="'
            . $node->iconSet->value . '">' . $thresholds . '</iconSet></cfRule>';
    }

    private function expressionRule(ExpressionFormatNode $node, FeatureContext $context): string
    {
        $dxfId = $context->styles->registerDxf($node->style);

        return '<cfRule type="expression" dxfId="' . $dxfId . '" priority="' . (++$this->priority) . '">'
            . '<formula>' . Xml::text($node->formula) . '</formula></cfRule>';
    }

    private function duplicateRule(DuplicateValuesNode $node, FeatureContext $context): string
    {
        $dxfId = $context->styles->registerDxf($node->style);
        $type = $node->unique ? 'uniqueValues' : 'duplicateValues';

        return '<cfRule type="' . $type . '" dxfId="' . $dxfId . '" priority="' . (++$this->priority) . '"/>';
    }
}
