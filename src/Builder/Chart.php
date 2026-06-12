<?php

declare(strict_types=1);

namespace PHPdot\Sheets\Builder;

use PHPdot\Sheets\Engine\Feature\Chart\ChartNode;
use PHPdot\Sheets\Engine\Feature\Chart\ChartSeries;
use PHPdot\Sheets\Engine\Feature\Chart\ChartType;
use PHPdot\Sheets\Engine\Feature\Chart\DataLabelPosition;
use PHPdot\Sheets\Engine\Feature\Chart\DataLabels;
use PHPdot\Sheets\Engine\Feature\Chart\LegendPosition;
use PHPdot\Sheets\Engine\Feature\FeatureNode;
use PHPdot\Sheets\Engine\Model\Color;
use PHPdot\Sheets\Engine\Support\InvalidArgumentException;
use PHPdot\Sheets\Engine\Support\RuntimeException;

/**
 * A chart being built on a sheet — returned by {@see Sheet::addChart()}. Type,
 * series, labels, legend, axis titles, data labels, and stacking are set fluently;
 * `->at()` places it. Committed to the engine when the sheet flushes.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class Chart implements FeatureBuilder
{
    use CellAnchor;

    private readonly ChartType $type;

    /** @var list<ChartSeries> */
    private array $series = [];
    private ?string $cell = null;
    private int $widthPx = 480;
    private int $heightPx = 288;
    private ?string $categories = null;
    private ?string $title = null;
    private ?LegendPosition $legend = null;
    private ?string $xAxisTitle = null;
    private ?string $yAxisTitle = null;
    private ?DataLabels $dataLabels = null;
    private bool $stacked = false;
    private bool $percentStacked = false;

    public function __construct(ChartType|string $type)
    {
        $this->type = $type instanceof ChartType ? $type : $this->toChartType($type);
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Add a data series referencing a worksheet range (e.g. 'Sales!$B$2:$B$10').
     * The name may be a range ref or literal text. `as` overrides the type for a
     * combo (bar/line/area only); `secondaryAxis` plots it on the right-hand scale.
     */
    public function series(
        string $valuesRef,
        ?string $name = null,
        Color|string|null $color = null,
        ChartType|string|null $as = null,
        bool $secondaryAxis = false,
    ): self {
        $this->series[] = new ChartSeries(
            $valuesRef,
            $name,
            $color === null ? null : $this->toColor($color),
            $as === null ? null : ($as instanceof ChartType ? $as : $this->toChartType($as)),
            $secondaryAxis,
        );

        return $this;
    }

    public function labels(string $categoriesRef): self
    {
        $this->categories = $categoriesRef;

        return $this;
    }

    /**
     * Legend position: a {@see LegendPosition} or one of right/left/top/bottom.
     */
    public function legend(LegendPosition|string $position): self
    {
        $this->legend = $position instanceof LegendPosition ? $position : $this->toLegend($position);

        return $this;
    }

    public function xTitle(string $title): self
    {
        $this->xAxisTitle = $title;

        return $this;
    }

    public function yTitle(string $title): self
    {
        $this->yAxisTitle = $title;

        return $this;
    }

    /**
     * Show data labels. Position: a {@see DataLabelPosition} or one of
     * center/insideEnd/insideBase/outsideEnd/bestFit.
     */
    public function dataLabels(
        bool $value = true,
        bool $category = false,
        bool $seriesName = false,
        bool $percent = false,
        DataLabelPosition|string|null $position = null,
    ): self {
        $this->dataLabels = new DataLabels(
            $value,
            $category,
            $seriesName,
            $percent,
            $position === null
                ? null
                : ($position instanceof DataLabelPosition ? $position : $this->toLabelPosition($position)),
        );

        return $this;
    }

    public function stacked(): self
    {
        $this->stacked = true;

        return $this;
    }

    public function stacked100(): self
    {
        $this->percentStacked = true;

        return $this;
    }

    /**
     * Place the chart with its top-left at an A1 cell, optionally sized in pixels.
     *
     * @param array{0: int, 1: int}|null $size [width, height] in pixels
     */
    public function at(string $cell, ?array $size = null): self
    {
        $this->cell = $cell;
        if ($size !== null) {
            $this->widthPx = $size[0];
            $this->heightPx = $size[1];
        }

        return $this;
    }

    public function toFeatureNode(): FeatureNode
    {
        if ($this->cell === null) {
            throw new RuntimeException('Chart needs a position — call ->at($cell).');
        }
        if ($this->series === []) {
            throw new RuntimeException('Chart needs at least one ->series(...).');
        }

        [$column, $row] = $this->parseCellRef($this->cell);

        return new ChartNode(
            type: $this->type,
            series: $this->series,
            column: $column,
            row: $row,
            widthPx: $this->widthPx,
            heightPx: $this->heightPx,
            categories: $this->categories,
            title: $this->title,
            legend: $this->legend,
            xAxisTitle: $this->xAxisTitle,
            yAxisTitle: $this->yAxisTitle,
            dataLabels: $this->dataLabels,
            stacked: $this->stacked,
            percentStacked: $this->percentStacked,
        );
    }

    private function toChartType(string $type): ChartType
    {
        return match (strtolower($type)) {
            'bar', 'column' => ChartType::Bar,
            'barh', 'barhorizontal' => ChartType::BarHorizontal,
            'line' => ChartType::Line,
            'pie' => ChartType::Pie,
            'area' => ChartType::Area,
            'doughnut', 'donut' => ChartType::Doughnut,
            'scatter' => ChartType::Scatter,
            default => throw new InvalidArgumentException(sprintf(
                'Unknown chart type "%s". Use bar, barh, line, pie, area, doughnut, or scatter.',
                $type,
            )),
        };
    }

    private function toLegend(string $position): LegendPosition
    {
        return match (strtolower($position)) {
            'right' => LegendPosition::Right,
            'left' => LegendPosition::Left,
            'top' => LegendPosition::Top,
            'bottom' => LegendPosition::Bottom,
            default => throw new InvalidArgumentException(sprintf(
                'Unknown legend position "%s". Use right, left, top, or bottom.',
                $position,
            )),
        };
    }

    private function toLabelPosition(string $position): DataLabelPosition
    {
        return match (strtolower($position)) {
            'center' => DataLabelPosition::Center,
            'insideend' => DataLabelPosition::InsideEnd,
            'insidebase' => DataLabelPosition::InsideBase,
            'outsideend' => DataLabelPosition::OutsideEnd,
            'bestfit' => DataLabelPosition::BestFit,
            default => throw new InvalidArgumentException(sprintf(
                'Unknown data-label position "%s". Use center, insideEnd, insideBase, outsideEnd, or bestFit.',
                $position,
            )),
        };
    }

    private function toColor(Color|string $color): Color
    {
        return $color instanceof Color ? $color : Color::hex($color);
    }
}
