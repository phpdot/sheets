<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Engine\Feature\Chart;

use PHPdot\Sheets\Engine\Support\Xml;

/**
 * Builds a DrawingML `chartSpace` document for a {@see ChartNode}: legend, axis
 * titles, rich data labels, stacking (incl. 100%), per-series colors, scatter
 * (X/Y) charts, horizontal bars, and combos — with an optional secondary value
 * axis (its own right-hand scale + hidden secondary category axis).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ChartXmlBuilder
{
    private const NS_C = 'http://schemas.openxmlformats.org/drawingml/2006/chart';
    private const NS_A = 'http://schemas.openxmlformats.org/drawingml/2006/main';
    private const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const CAT_AX_ID = 111111111;
    private const VAL_AX_ID = 222222222;
    private const SEC_VAL_AX_ID = 333333333;
    private const SEC_CAT_AX_ID = 444444444;

    public function build(ChartNode $node): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<c:chartSpace xmlns:c="' . self::NS_C . '" xmlns:a="' . self::NS_A . '" xmlns:r="' . self::NS_R . '">'
            . '<c:chart>'
            . $this->title($node->title)
            . '<c:plotArea><c:layout/>'
            . $this->plot($node)
            . '</c:plotArea>'
            . $this->legend($node->legend)
            . '<c:plotVisOnly val="1"/>'
            . '</c:chart>'
            . '</c:chartSpace>';
    }

    private function title(?string $title): string
    {
        if ($title === null) {
            return '<c:autoTitleDeleted val="1"/>';
        }

        return $this->richText($title) . '<c:autoTitleDeleted val="0"/>';
    }

    private function plot(ChartNode $node): string
    {
        return match ($node->type) {
            ChartType::Pie => '<c:pieChart><c:varyColors val="1"/>'
                . $this->allSeries($node, false) . $this->dataLabels($node) . '</c:pieChart>',
            ChartType::Doughnut => '<c:doughnutChart><c:varyColors val="1"/>'
                . $this->allSeries($node, false) . $this->dataLabels($node)
                . '<c:firstSliceAng val="0"/><c:holeSize val="50"/></c:doughnutChart>',
            ChartType::Scatter => '<c:scatterChart><c:scatterStyle val="lineMarker"/><c:varyColors val="0"/>'
                . $this->allSeries($node, true) . $this->dataLabels($node)
                . '<c:axId val="' . self::CAT_AX_ID . '"/><c:axId val="' . self::VAL_AX_ID . '"/></c:scatterChart>'
                . $this->scatterAxes($node),
            ChartType::Bar, ChartType::BarHorizontal, ChartType::Line, ChartType::Area => $this->comboPlot($node),
        };
    }

    /**
     * Group series by axis (primary/secondary) then effective type, emit one
     * chart block per group, and the matching axes.
     */
    private function comboPlot(ChartNode $node): string
    {
        /** @var array<string, array<int, ChartSeries>> $primary */
        $primary = [];
        /** @var array<string, array<int, ChartSeries>> $secondary */
        $secondary = [];
        foreach ($node->series as $index => $series) {
            $type = $series->type ?? $node->type;
            if ($series->secondaryAxis) {
                $secondary[$type->value][$index] = $series;
            } else {
                $primary[$type->value][$index] = $series;
            }
        }

        $blocks = '';
        foreach ($primary as $typeValue => $byIndex) {
            $blocks .= $this->axisBlock(ChartType::from($typeValue), $node, $this->seriesXml($node, $byIndex), self::CAT_AX_ID, self::VAL_AX_ID);
        }
        foreach ($secondary as $typeValue => $byIndex) {
            $blocks .= $this->axisBlock(ChartType::from($typeValue), $node, $this->seriesXml($node, $byIndex), self::SEC_CAT_AX_ID, self::SEC_VAL_AX_ID);
        }

        $axes = $this->primaryAxes($node);
        if ($secondary !== []) {
            $axes .= $this->secondaryAxes();
        }

        return $blocks . $axes;
    }

    /**
     * @param array<int, ChartSeries> $byIndex
     */
    private function seriesXml(ChartNode $node, array $byIndex): string
    {
        $xml = '';
        foreach ($byIndex as $index => $series) {
            $xml .= $this->ser($node, $series, $index, false);
        }

        return $xml;
    }

    private function axisBlock(ChartType $type, ChartNode $node, string $series, int $catAxId, int $valAxId): string
    {
        $axisIds = '<c:axId val="' . $catAxId . '"/><c:axId val="' . $valAxId . '"/>';
        $labels = $this->dataLabels($node);

        return match ($type) {
            ChartType::Bar => '<c:barChart><c:barDir val="col"/><c:grouping val="' . $this->grouping($node, 'clustered') . '"/>'
                . '<c:varyColors val="0"/>' . $series . $labels . $this->barOverlap($node) . $axisIds . '</c:barChart>',
            ChartType::BarHorizontal => '<c:barChart><c:barDir val="bar"/><c:grouping val="' . $this->grouping($node, 'clustered') . '"/>'
                . '<c:varyColors val="0"/>' . $series . $labels . $this->barOverlap($node) . $axisIds . '</c:barChart>',
            ChartType::Line => '<c:lineChart><c:grouping val="' . $this->grouping($node, 'standard') . '"/>'
                . '<c:varyColors val="0"/>' . $series . $labels . '<c:marker val="1"/>' . $axisIds . '</c:lineChart>',
            ChartType::Area => '<c:areaChart><c:grouping val="' . $this->grouping($node, 'standard') . '"/>'
                . '<c:varyColors val="0"/>' . $series . $labels . $axisIds . '</c:areaChart>',
            default => '',
        };
    }

    private function barOverlap(ChartNode $node): string
    {
        return $node->stacked || $node->percentStacked ? '<c:overlap val="100"/>' : '';
    }

    private function allSeries(ChartNode $node, bool $scatter): string
    {
        $xml = '';
        foreach ($node->series as $index => $series) {
            $xml .= $this->ser($node, $series, $index, $scatter);
        }

        return $xml;
    }

    private function ser(ChartNode $node, ChartSeries $series, int $index, bool $scatter): string
    {
        $xml = '<c:ser><c:idx val="' . $index . '"/><c:order val="' . $index . '"/>';
        if ($series->name !== null) {
            $xml .= $this->seriesName($series->name);
        }
        $xml .= $this->seriesColor($node, $series);

        if ($scatter) {
            if ($node->categories !== null) {
                $xml .= '<c:xVal><c:numRef><c:f>' . Xml::text($node->categories) . '</c:f></c:numRef></c:xVal>';
            }
            $xml .= '<c:yVal><c:numRef><c:f>' . Xml::text($series->valuesRef) . '</c:f></c:numRef></c:yVal>';
        } else {
            if ($node->categories !== null) {
                $xml .= '<c:cat><c:strRef><c:f>' . Xml::text($node->categories) . '</c:f></c:strRef></c:cat>';
            }
            $xml .= '<c:val><c:numRef><c:f>' . Xml::text($series->valuesRef) . '</c:f></c:numRef></c:val>';
        }

        return $xml . '</c:ser>';
    }

    /**
     * CT_SerTx is a choice: a worksheet reference goes in `strRef` (a formula),
     * a literal title in `c:v` — pushing a literal through `strRef` makes Excel
     * evaluate it as a name reference and lose the title.
     */
    private function seriesName(string $name): string
    {
        if ($this->isRangeRef($name)) {
            return '<c:tx><c:strRef><c:f>' . Xml::text($name) . '</c:f></c:strRef></c:tx>';
        }

        return '<c:tx><c:v>' . Xml::text($name) . '</c:v></c:tx>';
    }

    /**
     * Whether a series name is a worksheet range reference (`Sheet1!$B$1`,
     * `'My Sheet'!$B$1:$B$2`) rather than a literal title.
     */
    private function isRangeRef(string $name): bool
    {
        return preg_match(
            '/^(?:\'[^\']+\'|[A-Za-z_][A-Za-z0-9_.]*)!\$?[A-Z]{1,3}\$?\d+(?::\$?[A-Z]{1,3}\$?\d+)?$/',
            $name,
        ) === 1;
    }

    private function seriesColor(ChartNode $node, ChartSeries $series): string
    {
        if ($series->color === null) {
            return '';
        }

        $fill = '<a:solidFill><a:srgbClr val="' . $series->color->rgb . '"/></a:solidFill>';
        $type = $series->type ?? $node->type;
        if ($type === ChartType::Line || $type === ChartType::Scatter) {
            return '<c:spPr><a:ln>' . $fill . '</a:ln></c:spPr>';
        }

        return '<c:spPr>' . $fill . '</c:spPr>';
    }

    private function grouping(ChartNode $node, string $default): string
    {
        if ($node->percentStacked) {
            return 'percentStacked';
        }
        if ($node->stacked) {
            return 'stacked';
        }

        return $default;
    }

    private function dataLabels(ChartNode $node): string
    {
        $labels = $node->dataLabels;
        if ($labels === null) {
            return '';
        }

        $xml = '<c:dLbls>';
        if ($labels->position !== null) {
            $xml .= '<c:dLblPos val="' . $labels->position->value . '"/>';
        }
        $xml .= '<c:showLegendKey val="0"/>'
            . '<c:showVal val="' . ($labels->value ? '1' : '0') . '"/>'
            . '<c:showCatName val="' . ($labels->category ? '1' : '0') . '"/>'
            . '<c:showSerName val="' . ($labels->seriesName ? '1' : '0') . '"/>'
            . '<c:showPercent val="' . ($labels->percent ? '1' : '0') . '"/>'
            . '<c:showBubbleSize val="0"/>';

        return $xml . '</c:dLbls>';
    }

    private function legend(?LegendPosition $position): string
    {
        if ($position === null) {
            return '';
        }

        return '<c:legend><c:legendPos val="' . $position->value . '"/><c:overlay val="0"/></c:legend>';
    }

    private function primaryAxes(ChartNode $node): string
    {
        $horizontal = $node->type === ChartType::BarHorizontal;
        $catPos = $horizontal ? 'l' : 'b';
        $valPos = $horizontal ? 'b' : 'l';

        return $this->catAx(self::CAT_AX_ID, $catPos, self::VAL_AX_ID, $node->xAxisTitle, false)
            . $this->valAx(self::VAL_AX_ID, $valPos, self::CAT_AX_ID, $node->yAxisTitle, false, null);
    }

    private function secondaryAxes(): string
    {
        // A right-hand value axis crossing a hidden secondary category axis at its max.
        return $this->valAx(self::SEC_VAL_AX_ID, 'r', self::SEC_CAT_AX_ID, null, false, 'max')
            . $this->catAx(self::SEC_CAT_AX_ID, 'b', self::SEC_VAL_AX_ID, null, true);
    }

    private function scatterAxes(ChartNode $node): string
    {
        return $this->valAx(self::CAT_AX_ID, 'b', self::VAL_AX_ID, $node->xAxisTitle, false, null)
            . $this->valAx(self::VAL_AX_ID, 'l', self::CAT_AX_ID, $node->yAxisTitle, false, null);
    }

    private function catAx(int $id, string $pos, int $crossId, ?string $title, bool $hidden): string
    {
        return '<c:catAx><c:axId val="' . $id . '"/><c:scaling><c:orientation val="minMax"/></c:scaling>'
            . '<c:delete val="' . ($hidden ? '1' : '0') . '"/><c:axPos val="' . $pos . '"/>' . $this->axisTitle($title)
            . '<c:crossAx val="' . $crossId . '"/></c:catAx>';
    }

    private function valAx(int $id, string $pos, int $crossId, ?string $title, bool $hidden, ?string $crosses): string
    {
        return '<c:valAx><c:axId val="' . $id . '"/><c:scaling><c:orientation val="minMax"/></c:scaling>'
            . '<c:delete val="' . ($hidden ? '1' : '0') . '"/><c:axPos val="' . $pos . '"/>' . $this->axisTitle($title)
            . '<c:crossAx val="' . $crossId . '"/>' . ($crosses !== null ? '<c:crosses val="' . $crosses . '"/>' : '')
            . '</c:valAx>';
    }

    private function axisTitle(?string $title): string
    {
        return $title !== null ? $this->richText($title) : '';
    }

    private function richText(string $text): string
    {
        return '<c:title><c:tx><c:rich><a:bodyPr/><a:lstStyle/><a:p><a:r><a:t>'
            . Xml::text($text)
            . '</a:t></a:r></a:p></c:rich></c:tx><c:overlay val="0"/></c:title>';
    }
}
